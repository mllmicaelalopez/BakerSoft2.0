<?php

declare(strict_types=1);

/**
 * BakerSoft - Informes (HU-14, Sprint 5: Listados e Informes).
 *
 * Informe de productos más vendidos: ranking de los productos de los pedidos
 * "Entregado" de un período, con indicadores, gráfico de los 10 primeros,
 * comparativo con el período anterior y descarga en PDF (Dompdf). Usa los
 * datos de siempre (pedido, detalle_pedido, producto, tipo_producto): este
 * sprint no crea entidades nuevas.
 *
 * Permisos: masVendidos() y masVendidosPdf() usan
 * requireRole('informe-mas-vendidos') — solo administrador y maestro panadero,
 * porque es información de gestión (ver src/config/permisos.php). A cajero y
 * repartidor el servidor les responde 403 aunque escriban la dirección a mano.
 *
 * Va en un controlador propio y no dentro de un ABM: las rutas son fijas
 * ("informes/mas-vendidos" y "informes/mas-vendidos/pdf") y van en el switch
 * de public/index.php, como "entregas" (EntregaController).
 *
 * La consulta (condición común, ranking y conteo) vive en
 * Pedido::rankingMasVendidos() y Pedido::contarEntregadosPeriodo(). La
 * pantalla y el PDF arman sus datos con el mismo método (armarInforme()), así
 * que nunca pueden mostrar números distintos; el bloque "Más vendidos…" del
 * Inicio usa la misma consulta con el período "Últimos 7 días"
 * (rangoUltimos7()).
 */
final class InformeController
{
    /** Períodos del panel: clave de la query string => texto del select. */
    private const PERIODOS = [
        'ultimos-7'       => 'Últimos 7 días',
        'este-mes'        => 'Este mes',
        'mes-anterior'    => 'Mes anterior',
        'ultimos-3-meses' => 'Últimos 3 meses',
        'personalizado'   => 'Rango personalizado',
    ];

    private const PERIODO_POR_DEFECTO = 'este-mes';

    /** Clave de $_SESSION donde quedan los últimos parámetros con los que se generó el informe. */
    private const CLAVE_SESION = 'informe_mas_vendidos';

    /** Cantidad de productos del gráfico de barras. */
    private const TOP_GRAFICO = 10;

    private const ERROR_PERIODO = 'El período seleccionado no es válido';

    private const ERROR_FECHA_FORMATO = 'La fecha ingresada no es válida';

    private const ERROR_FECHA_ORDEN = 'La fecha desde no puede ser posterior a la fecha hasta';

    private const ERROR_CATEGORIA = 'La categoría seleccionada no existe';

    /**
     * Rango de "Últimos 7 días" (hoy y los 6 días anteriores). Lo usa también
     * el Inicio (DashboardController), para que su bloque de más vendidos
     * muestre lo mismo que el informe con ese período.
     *
     * @return array{desde: string, hasta: string}
     */
    public static function rangoUltimos7(): array
    {
        $periodos = self::calcularPeriodos('ultimos-7', new \DateTimeImmutable('today'));

        return ['desde' => $periodos['desde'], 'hasta' => $periodos['hasta']];
    }

    /**
     * Fechas efectivas de un período (regla 3 de HU-14) y las de su período
     * anterior, el que se usa para comparar (regla 4). Todo "Y-m-d", con el
     * día "hasta" inclusive.
     *
     * Es público y recibe "hoy" por parámetro para poder probarlo con fechas
     * fijas, sin cambiar el reloj del sistema.
     *
     * @param string|null $desdePersonalizado Solo para "personalizado" ("Y-m-d" ya validado).
     * @param string|null $hastaPersonalizado Solo para "personalizado" ("Y-m-d" ya validado).
     * @return array{desde: string, hasta: string, anterior_desde: string, anterior_hasta: string}
     */
    public static function calcularPeriodos(
        string $periodo,
        \DateTimeImmutable $hoy,
        ?string $desdePersonalizado = null,
        ?string $hastaPersonalizado = null
    ): array {
        $hoy = $hoy->setTime(0, 0);

        // Día 1 del mes de hoy. Partir siempre del día 1 evita los desbordes de
        // PHP al sumar o restar meses ("31 de marzo - 1 mes" daría el 3 de marzo).
        $primerDiaMes = $hoy->setDate((int) $hoy->format('Y'), (int) $hoy->format('n'), 1);

        switch ($periodo) {
            case 'ultimos-7':
                // Hoy y los 6 días anteriores.
                $desde = $hoy->modify('-6 days');
                $hasta = $hoy;
                break;

            case 'este-mes':
                // Del día 1 hasta hoy. Se compara con los mismos días del mes
                // anterior: del 1 al mismo número de día o, si ese día no
                // existe en el mes anterior, hasta su último día (31/03 ->
                // 01/02 al 28/02).
                $desde = $primerDiaMes;
                $hasta = $hoy;

                $anteriorDesde = $primerDiaMes->modify('-1 month');
                $dia           = min((int) $hoy->format('j'), (int) $anteriorDesde->format('t'));
                $anteriorHasta = $anteriorDesde->setDate((int) $anteriorDesde->format('Y'), (int) $anteriorDesde->format('n'), $dia);

                return self::armarPeriodos($desde, $hasta, $anteriorDesde, $anteriorHasta);

            case 'mes-anterior':
                // El mes calendario completo previo. Se compara con el mes
                // completo previo a ese.
                $desde = $primerDiaMes->modify('-1 month');
                $hasta = $desde->modify('last day of this month');

                $anteriorDesde = $desde->modify('-1 month');
                $anteriorHasta = $anteriorDesde->modify('last day of this month');

                return self::armarPeriodos($desde, $hasta, $anteriorDesde, $anteriorHasta);

            case 'ultimos-3-meses':
                // Desde el mismo día de hace 3 meses hasta hoy. Si ese día no
                // existe en el mes de hace 3 meses (31/05 -> "31/02"), se toma
                // su último día.
                $mesInicio = $primerDiaMes->modify('-3 months');
                $dia       = min((int) $hoy->format('j'), (int) $mesInicio->format('t'));
                $desde     = $mesInicio->setDate((int) $mesInicio->format('Y'), (int) $mesInicio->format('n'), $dia);
                $hasta     = $hoy;
                break;

            default: // personalizado
                $desde = (new \DateTimeImmutable((string) $desdePersonalizado))->setTime(0, 0);
                $hasta = (new \DateTimeImmutable((string) $hastaPersonalizado))->setTime(0, 0);
                break;
        }

        // Últimos 7 días, últimos 3 meses y rango personalizado se comparan con
        // la misma cantidad de días inmediatamente anterior al período elegido.
        $dias          = $desde->diff($hasta)->days + 1;
        $anteriorHasta = $desde->modify('-1 day');
        $anteriorDesde = $anteriorHasta->modify(sprintf('-%d days', $dias - 1));

        return self::armarPeriodos($desde, $hasta, $anteriorDesde, $anteriorHasta);
    }

    /**
     * @return array{desde: string, hasta: string, anterior_desde: string, anterior_hasta: string}
     */
    private static function armarPeriodos(
        \DateTimeImmutable $desde,
        \DateTimeImmutable $hasta,
        \DateTimeImmutable $anteriorDesde,
        \DateTimeImmutable $anteriorHasta
    ): array {
        return [
            'desde'          => $desde->format('Y-m-d'),
            'hasta'          => $hasta->format('Y-m-d'),
            'anterior_desde' => $anteriorDesde->format('Y-m-d'),
            'anterior_hasta' => $anteriorHasta->format('Y-m-d'),
        ];
    }

    /**
     * GET /informes/mas-vendidos
     *
     * Si la pantalla se abre sin ningún parámetro en la query string, usa los
     * últimos con los que se generó el informe en esta sesión (HU-14, criterio
     * 7) o, si no hay, los por defecto (este mes, todas las categorías, sin
     * comparar).
     *
     * Con parámetros inválidos responde 400 pero muestra la pantalla con el
     * mensaje de validación en el panel (el usuario ve el mensaje, nunca el
     * código), sin indicadores, tabla ni gráfico y sin consultar el informe.
     */
    public function masVendidos(): void
    {
        requireAuth();
        requireRole('informe-mas-vendidos');

        $entrada = $_GET;

        if ($entrada === [] && is_array($_SESSION[self::CLAVE_SESION] ?? null)) {
            $entrada = $_SESSION[self::CLAVE_SESION];
        }

        $parametros = $this->leerParametros($entrada);

        $datos = [
            'slot'               => 'informe/mas-vendidos',
            'titulo'             => 'Productos más vendidos',
            'menuActivo'         => 'mas-vendidos',
            'periodos'           => self::PERIODOS,
            'categorias'         => $this->categoriasParaElPanel($parametros['categoria']),
            'periodo'            => $parametros['periodo'],
            'filtroDesde'        => $parametros['desde'],
            'filtroHasta'        => $parametros['hasta'],
            'filtroCategoria'    => $parametros['categoria_ingresada'],
            'comparar'           => $parametros['comparar'],
            'error'              => $parametros['error'],
            'informe'            => null,
            'queryPdf'           => '',
        ];

        if ($parametros['error'] !== '') {
            http_response_code(400);
            view('layouts/app-layout', $datos);
            return;
        }

        // Solo se recuerdan los parámetros válidos.
        $_SESSION[self::CLAVE_SESION] = $parametros['para_sesion'];

        $informe = $this->armarInforme($parametros);

        view('layouts/app-layout', array_merge($datos, [
            'informe'  => $informe,
            'queryPdf' => $this->queryDeParametros($parametros),
        ]));
    }

    /**
     * GET /informes/mas-vendidos/pdf
     *
     * Mismos parámetros y misma validación que la pantalla, pero siempre de la
     * query string (el botón "Descargar PDF" los pasa explícitos; no se leen de
     * la sesión). Con un error responde 400 en texto plano, sin archivo.
     *
     * Dompdf es una dependencia de Composer: si falta (no se corrió composer
     * install) responde 500 con un mensaje claro en vez de un error fatal.
     */
    public function masVendidosPdf(): void
    {
        requireAuth();
        requireRole('informe-mas-vendidos');

        $parametros = $this->leerParametros($_GET);

        if ($parametros['error'] !== '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo $parametros['error'];
            return;
        }

        if (!class_exists(\Dompdf\Dompdf::class)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Para descargar el PDF hay que instalar las dependencias con composer install';
            return;
        }

        $informe = $this->armarInforme($parametros);

        // La plantilla se renderiza a un string (view() imprime directo).
        ob_start();
        view('informe/mas-vendidos-pdf', ['informe' => $informe]);
        $html = (string) ob_get_clean();

        $opciones = new \Dompdf\Options();
        // DejaVu Sans trae los acentos y los símbolos ▲ ▼ de las variaciones.
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);

        $dompdf = new \Dompdf\Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = $dompdf->output();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="productos-mas-vendidos-' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));

        echo $pdf;

        app_log(sprintf(
            'INFORME MAS VENDIDOS PDF usuario=%s periodo=%s_%s filas=%d',
            usuario_actual()['email'] ?? '',
            $informe['desde'],
            $informe['hasta'],
            count($informe['filas'])
        ));
    }

    /* -----------------------------------------------------------------------
       Helpers privados
       ----------------------------------------------------------------------- */

    /**
     * Lee y valida los parámetros (pantalla y PDF). Un error es un mensaje
     * para mostrar; el orden en que se revisan es período, fechas y categoría.
     *
     * 'desde' y 'hasta' son lo que hay que mostrar en el panel: las fechas
     * efectivas del período si todo está bien, o lo que escribió el usuario si
     * hubo error. 'periodos' (las fechas efectivas, con las del período
     * anterior) solo viene si no hay error.
     *
     * @param array<string, mixed> $entrada $_GET, o lo guardado en la sesión.
     * @return array{
     *     error: string, periodo: string, desde: string, hasta: string,
     *     categoria: ?int, categoria_ingresada: string, comparar: bool,
     *     periodos: ?array{desde: string, hasta: string, anterior_desde: string, anterior_hasta: string},
     *     para_sesion: array<string, string>
     * }
     */
    private function leerParametros(array $entrada): array
    {
        $texto = static fn (string $clave): string => is_string($entrada[$clave] ?? null) ? trim($entrada[$clave]) : '';

        // Un valor que no es texto (periodo[]=x) no es un período válido.
        $periodo = isset($entrada['periodo']) && !is_string($entrada['periodo'])
            ? '?'
            : ($texto('periodo') !== '' ? $texto('periodo') : self::PERIODO_POR_DEFECTO);

        $desdeIngresado = $texto('fecha_desde');
        $hastaIngresado = $texto('fecha_hasta');

        // Igual que el período: una categoría que no es texto (categoria[]=1)
        // no es una categoría que exista.
        $categoriaIngresada = isset($entrada['categoria']) && !is_string($entrada['categoria']) ? '?' : $texto('categoria');
        $comparar           = $texto('comparar') === '1';

        $error = '';

        if (!isset(self::PERIODOS[$periodo])) {
            $error = self::ERROR_PERIODO;
        } elseif ($periodo === 'personalizado') {
            if (!$this->esFechaValida($desdeIngresado) || !$this->esFechaValida($hastaIngresado)) {
                $error = self::ERROR_FECHA_FORMATO;
            } elseif ($desdeIngresado > $hastaIngresado) {
                $error = self::ERROR_FECHA_ORDEN; // "Y-m-d" se ordena bien como texto
            }
        }

        $categoria = null;

        if ($error === '' && $categoriaIngresada !== '') {
            if (ctype_digit($categoriaIngresada) && TipoProducto::buscarPorId((int) $categoriaIngresada) !== null) {
                $categoria = (int) $categoriaIngresada;
            } else {
                $error = self::ERROR_CATEGORIA;
            }
        }

        $periodos = $error === ''
            ? self::calcularPeriodos($periodo, new \DateTimeImmutable('today'), $desdeIngresado, $hastaIngresado)
            : null;

        return [
            'error'               => $error,
            'periodo'             => isset(self::PERIODOS[$periodo]) ? $periodo : self::PERIODO_POR_DEFECTO,
            'desde'               => $periodos['desde'] ?? $desdeIngresado,
            'hasta'               => $periodos['hasta'] ?? $hastaIngresado,
            'categoria'           => $categoria,
            'categoria_ingresada' => $categoriaIngresada,
            'comparar'            => $comparar,
            'periodos'            => $periodos,
            'para_sesion'         => [
                'periodo'     => $periodo,
                'fecha_desde' => $periodo === 'personalizado' ? $desdeIngresado : '',
                'fecha_hasta' => $periodo === 'personalizado' ? $hastaIngresado : '',
                'categoria'   => $categoriaIngresada,
                'comparar'    => $comparar ? '1' : '',
            ],
        ];
    }

    /**
     * "2026-09-20", con el formato exacto (evita que "2026-9-2" o "2026-13-45"
     * se cuelen en la consulta SQL). Mismo criterio que EntregaController.
     */
    private function esFechaValida(string $fecha): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $dt !== false && $dt->format('Y-m-d') === $fecha;
    }

    /**
     * Categorías del select del panel: las activas y, si la elegida existe pero
     * está dada de baja, también esa (para que el select no la pierda).
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoriasParaElPanel(?int $idElegida): array
    {
        $categorias = TipoProducto::listar(null, 'activo');

        if ($idElegida !== null) {
            foreach ($categorias as $categoria) {
                if ((int) $categoria['id_tipo_producto'] === $idElegida) {
                    return $categorias;
                }
            }

            $elegida = TipoProducto::buscarPorId($idElegida);

            if ($elegida !== null) {
                $categorias[] = $elegida;
            }
        }

        return $categorias;
    }

    /**
     * Query string con los parámetros efectivos, para el botón "Descargar PDF":
     * las fechas solo viajan con "Rango personalizado" (las de los demás
     * períodos las calcula el servidor).
     *
     * @param array<string, mixed> $parametros
     */
    private function queryDeParametros(array $parametros): string
    {
        $query = [
            'periodo'     => $parametros['periodo'],
            'fecha_desde' => $parametros['periodo'] === 'personalizado' ? $parametros['desde'] : '',
            'fecha_hasta' => $parametros['periodo'] === 'personalizado' ? $parametros['hasta'] : '',
            'categoria'   => $parametros['categoria'] !== null ? (string) $parametros['categoria'] : '',
            'comparar'    => $parametros['comparar'] ? '1' : '',
        ];

        return http_build_query(array_filter($query, static fn ($valor): bool => $valor !== ''));
    }

    /**
     * Arma todos los datos del informe a partir de parámetros ya validados. Lo
     * usan la pantalla y el PDF, así que no pueden diferir (criterio 8 de la
     * documentación: el PDF muestra exactamente lo mismo que la pantalla).
     *
     * @param array<string, mixed> $parametros Resultado de leerParametros(), sin error.
     * @return array<string, mixed>
     */
    private function armarInforme(array $parametros): array
    {
        $periodos  = $parametros['periodos'];
        $categoria = $parametros['categoria'];
        $comparar  = $parametros['comparar'];

        // Todo lo que sigue sale de la base ya agrupado y contado; acá solo se
        // da formato y se calculan los porcentajes y las variaciones.
        $ranking         = Pedido::rankingMasVendidos($periodos['desde'], $periodos['hasta'], $categoria);
        $totalEntregados = Pedido::contarEntregadosPeriodo($periodos['desde'], $periodos['hasta'], $categoria);

        // Comparativo: solo se arma si el usuario lo pidió.
        //
        // Los productos que se vendieron únicamente en el período anterior NO
        // entran al ranking: el ranking es de lo vendido en el período
        // elegido, y el anterior solo aporta la columna de comparación de
        // cada producto de ese ranking.
        $anteriorPorProducto = [];
        $totalAnterior       = null;

        if ($comparar) {
            foreach (Pedido::rankingMasVendidos($periodos['anterior_desde'], $periodos['anterior_hasta'], $categoria) as $fila) {
                $anteriorPorProducto[(int) $fila['id_producto']] = (int) $fila['pedidos'];
            }

            $totalAnterior = Pedido::contarEntregadosPeriodo($periodos['anterior_desde'], $periodos['anterior_hasta'], $categoria);
        }

        $filas = [];

        foreach ($ranking as $indice => $fila) {
            $pedidos  = (int) $fila['pedidos'];
            $anterior = $comparar ? ($anteriorPorProducto[(int) $fila['id_producto']] ?? 0) : null;

            $filas[] = [
                'posicion'   => $indice + 1,
                'producto'   => (string) $fila['producto'],
                'categoria'  => (string) $fila['categoria'],
                'pedidos'    => $pedidos,
                'total'      => formatear_cantidad((string) $fila['total'], (string) $fila['unidad_medida']),
                'porcentaje' => $totalEntregados > 0 ? (int) round($pedidos / $totalEntregados * 100) : 0,
                'anterior'   => $anterior,
                'variacion'  => $anterior !== null ? $this->variacion($pedidos, $anterior) : null,
            ];
        }

        $primero = $filas[0] ?? null;

        return [
            'desde'             => $periodos['desde'],
            'hasta'             => $periodos['hasta'],
            'texto_periodo'     => 'Período: ' . $this->fecha($periodos['desde']) . ' al ' . $this->fecha($periodos['hasta']),
            'texto_comparado'   => $comparar
                ? 'comparado con ' . $this->fecha($periodos['anterior_desde']) . ' al ' . $this->fecha($periodos['anterior_hasta'])
                : '',
            'generado'          => 'Generado: ' . date('d/m/Y H:i'),
            'categoria'         => $categoria !== null ? (string) (TipoProducto::buscarPorId($categoria)['nombre'] ?? '') : null,
            'comparar'          => $comparar,
            'hay_datos'         => $filas !== [],
            'indicadores'       => [
                'entregados'    => [
                    'valor'     => $totalEntregados,
                    'anterior'  => $totalAnterior,
                    'variacion' => $totalAnterior !== null ? $this->variacion($totalEntregados, $totalAnterior) : null,
                ],
                'distintos'     => [
                    'valor'     => count($filas),
                    'activos'   => Producto::contarActivos($categoria),
                ],
                'mas_vendido'   => [
                    'nombre'    => $primero !== null ? $primero['producto'] : '—',
                    'detalle'   => $primero !== null
                        ? sprintf('en %d de %d pedidos (%d %%)', $primero['pedidos'], $totalEntregados, $primero['porcentaje'])
                        : '',
                ],
            ],
            'filas'             => $filas,
            'grafico'           => $this->datosDelGrafico($filas, $comparar),
        ];
    }

    /**
     * Variación de un valor contra el del período anterior (regla 5 de HU-14).
     *
     * Con período anterior en 0 NO se calcula el porcentaje (no se divide por
     * cero): la variación absoluta es la cantidad actual, el porcentaje es
     * null y se marca 'nuevo' (la pantalla y el PDF muestran la etiqueta
     * "Nuevo"). Si los dos períodos están en 0 no hay nada nuevo: queda "= 0
     * (0 %)". Nunca sale un error, INF, NAN ni un porcentaje inventado.
     *
     * @return array{tipo: string, flecha: string, absoluta: string, porcentaje: ?string, nuevo: bool, texto_accesible: string}
     */
    private function variacion(int $actual, int $anterior): array
    {
        $diferencia = $actual - $anterior;
        $nuevo      = $anterior === 0 && $actual > 0;
        $porcentaje = $anterior > 0 ? (int) round($diferencia / $anterior * 100) : 0;

        $conSigno = static fn (int $numero): string => ($numero > 0 ? '+' : '') . $numero;

        if ($diferencia > 0) {
            $tipo = 'sube';
        } elseif ($diferencia < 0) {
            $tipo = 'baja';
        } else {
            $tipo = 'igual';
        }

        return [
            'tipo'            => $tipo,
            'flecha'          => ['sube' => '▲', 'baja' => '▼', 'igual' => '='][$tipo],
            'absoluta'        => $conSigno($diferencia),
            'porcentaje'      => $nuevo ? null : $conSigno($porcentaje) . ' %',
            'nuevo'           => $nuevo,
            'texto_accesible' => $nuevo ? 'nuevo' : ['sube' => 'subió', 'baja' => 'bajó', 'igual' => 'sin cambios'][$tipo],
        ];
    }

    /**
     * Barras de los 10 primeros: una por producto (o dos, con comparativo). El
     * ancho de cada una es proporcional al valor más alto que se dibuja, así
     * las dos series comparten escala.
     *
     * @param array<int, array<string, mixed>> $filas
     * @return array<int, array{producto: string, actual: int, anterior: ?int, ancho_actual: float, ancho_anterior: ?float}>
     */
    private function datosDelGrafico(array $filas, bool $comparar): array
    {
        $primeras = array_slice($filas, 0, self::TOP_GRAFICO);

        $maximo = 1;

        foreach ($primeras as $fila) {
            $maximo = max($maximo, $fila['pedidos'], $comparar ? (int) $fila['anterior'] : 0);
        }

        return array_map(static fn (array $fila): array => [
            'producto'       => $fila['producto'],
            'actual'         => $fila['pedidos'],
            'anterior'       => $comparar ? (int) $fila['anterior'] : null,
            'ancho_actual'   => round($fila['pedidos'] / $maximo * 100, 1),
            'ancho_anterior' => $comparar ? round((int) $fila['anterior'] / $maximo * 100, 1) : null,
        ], $primeras);
    }

    /**
     * "2026-09-20" -> "20/09/2026".
     */
    private function fecha(string $fechaIso): string
    {
        return (new \DateTimeImmutable($fechaIso))->format('d/m/Y');
    }
}
