<?php

declare(strict_types=1);

/**
 * BakerSoft - Próximas entregas (HU-13, Sprint 5: Listados e Informes).
 *
 * Listado de solo lectura de los pedidos "Confirmado", "En preparación" o
 * "Listo" que hay que entregar en los próximos días (más los atrasados), con
 * filtros, paginación, indicador de urgencia y exportación a CSV. Los datos
 * son los de siempre (Pedido, detalle_pedido, cliente, producto): este sprint
 * no crea entidades nuevas.
 *
 * Permisos: index() y exportar() usan requireRole('pedido-consulta') — los
 * cuatro roles pueden consultar, igual que el listado de Pedidos (HU-11). No
 * hace falta una capacidad nueva en src/config/permisos.php.
 *
 * Va en un controlador propio y no dentro de PedidoController porque las
 * rutas que empiezan con "pedido/" las despacha despachar_abm() de
 * public/index.php, que responde 404 a cualquier subruta que no sea del ABM.
 * Acá las rutas son fijas ("entregas" y "entregas/exportar") y van en el
 * switch del front controller, como "dashboard".
 *
 * La consulta (filtros, orden, paginación, conteo, resumen de productos y
 * urgencia) vive en Pedido::listarProximasEntregas() y
 * Pedido::contarProximasEntregas(); el Inicio usa la misma consulta con los
 * filtros por defecto (filtrosPorDefecto()).
 */
final class EntregaController
{
    /** Pedidos por página del listado. */
    private const POR_PAGINA = 25;

    /** Días hacia adelante del rango por defecto (hoy + 7). */
    private const DIAS_POR_DEFECTO = 7;

    /** Estados que entran al listado (regla 1 de HU-13): "Nuevo" no, todavía no está confirmado. */
    private const ESTADOS = ['Confirmado', 'En preparación', 'Listo'];

    private const ERROR_FECHA_ORDEN = 'La fecha desde no puede ser posterior a la fecha hasta';

    private const ERROR_FECHA_FORMATO = 'La fecha ingresada no es válida';

    /**
     * Rango de fechas por defecto: desde hoy hasta hoy + 7 días. Lo usa
     * también el Inicio (DashboardController), para que su bloque de
     * próximas entregas muestre lo mismo que este listado sin filtros.
     *
     * @return array{fecha_desde: string, fecha_hasta: string}
     */
    public static function filtrosPorDefecto(): array
    {
        $hoy = new \DateTime('today');

        return [
            'fecha_desde' => $hoy->format('Y-m-d'),
            'fecha_hasta' => (clone $hoy)->modify(sprintf('+%d days', self::DIAS_POR_DEFECTO))->format('Y-m-d'),
        ];
    }

    /**
     * GET /entregas
     *
     * Los filtros se aplican con un GET normal y recarga completa (igual que
     * Producto y Tipo de Producto, no con el fetch parcial de Pedidos): la
     * paginación y el botón de exportar dependen de la query string, y así
     * todo queda en la URL.
     *
     * Con un error en las fechas responde 400 y muestra la pantalla con el
     * mensaje, sin listado y sin consultar la base (criterio de HU-13: el
     * servidor no depende de que el frontend ya haya validado).
     */
    public function index(): void
    {
        requireAuth();
        requireRole('pedido-consulta');

        $lectura = $this->leerFiltros();
        $filtros = $lectura['filtros'];

        $datos = [
            'slot'          => 'entrega/index',
            'titulo'        => 'Próximas entregas',
            'menuActivo'    => 'entregas',
            'tiposPedido'   => TipoPedido::listar(),
            'estados'       => self::ESTADOS,
            'filtroCliente' => $filtros['cliente_nombre'] ?? '',
            'filtroTipo'    => isset($filtros['id_tipo_pedido']) ? (string) $filtros['id_tipo_pedido'] : '',
            'filtroEstado'  => $filtros['estado'] ?? '',
            'filtroDesde'   => $lectura['desde'],
            'filtroHasta'   => $lectura['hasta'],
            'error'         => $lectura['error'],
            'entregas'      => [],
            'total'         => 0,
            'pagina'        => 1,
            'totalPaginas'  => 1,
            'queryFiltros'  => '',
        ];

        if ($lectura['error'] !== '') {
            http_response_code(400);
            view('layouts/app-layout', $datos);
            return;
        }

        $total        = Pedido::contarProximasEntregas($filtros);
        $totalPaginas = max(1, (int) ceil($total / self::POR_PAGINA));

        // Un número de página raro vale 1; uno que se pasa de la última, la última.
        $pagina = (string) ($_GET['pagina'] ?? '');
        $pagina = ctype_digit($pagina) && (int) $pagina >= 1 ? (int) $pagina : 1;
        $pagina = min($pagina, $totalPaginas);

        view('layouts/app-layout', array_merge($datos, [
            'entregas'     => Pedido::listarProximasEntregas($filtros, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA),
            'total'        => $total,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'queryFiltros' => $this->queryDeFiltros($filtros),
        ]));
    }

    /**
     * GET /entregas/exportar
     *
     * CSV con exactamente los mismos filtros y el mismo orden que el listado,
     * pero sin paginar (todas las filas). Arriba lleva tres filas de
     * metadatos: título, fecha y hora de generación y filtros aplicados.
     *
     * BOM UTF-8 y separador ";" para que Excel en castellano lo abra bien,
     * con acentos y en columnas.
     */
    public function exportar(): void
    {
        requireAuth();
        requireRole('pedido-consulta');

        $lectura = $this->leerFiltros();

        if ($lectura['error'] !== '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo $lectura['error'];
            return;
        }

        $filtros  = $lectura['filtros'];
        $entregas = Pedido::listarProximasEntregas($filtros);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="proximas-entregas-' . date('Y-m-d') . '.csv"');

        echo "\xEF\xBB\xBF";

        $salida = fopen('php://output', 'w');

        fputcsv($salida, ['Próximas entregas - BakerSoft'], ';');
        fputcsv($salida, ['Generado: ' . date('d/m/Y H:i')], ';');
        fputcsv($salida, [$this->textoDeFiltros($filtros)], ';');
        fputcsv($salida, [], ';');

        fputcsv($salida, [
            'N° de pedido', 'Fecha y hora de entrega', 'Cliente', 'Teléfono', 'Dirección',
            'Tipo de pedido', 'Productos', 'Estado', 'Urgencia',
        ], ';');

        foreach ($entregas as $entrega) {
            fputcsv($salida, [
                (string) $entrega['id_pedido'],
                date('d/m/Y H:i', strtotime((string) $entrega['fecha_entrega'])),
                (string) $entrega['cliente'],
                (string) ($entrega['telefono'] ?? ''),
                (string) ($entrega['direccion'] ?? ''),
                (string) $entrega['tipo_pedido'],
                (string) ($entrega['productos'] ?? ''),
                (string) $entrega['estado'],
                (string) $entrega['urgencia'],
            ], ';');
        }

        fclose($salida);

        app_log(sprintf(
            'ENTREGAS EXPORTAR CSV usuario=%s filas=%d',
            usuario_actual()['email'] ?? '',
            count($entregas)
        ));
    }

    /* -----------------------------------------------------------------------
       Helpers privados
       ----------------------------------------------------------------------- */

    /**
     * Lee y valida los filtros de la query string (mismo criterio que
     * PedidoController::filtrosDesdeQuery()): un estado, un tipo o un cliente
     * raros se descartan en vez de dar error; las fechas, en cambio, sí
     * pueden dar error (formato inválido o "desde" posterior a "hasta").
     *
     * Las fechas que vienen vacías o no vienen toman el valor por defecto
     * (hoy y hoy + 7). 'desde' y 'hasta' del resultado son lo que hay que
     * mostrar en los campos de fecha: los valores efectivos si todo está bien,
     * o lo que escribió el usuario si hubo error.
     *
     * @return array{
     *     filtros: array<string, int|string>, error: string, desde: string, hasta: string
     * }
     */
    private function leerFiltros(): array
    {
        $porDefecto = self::filtrosPorDefecto();

        $desdeIngresado = trim((string) ($_GET['fecha_desde'] ?? ''));
        $hastaIngresado = trim((string) ($_GET['fecha_hasta'] ?? ''));

        $desde = $desdeIngresado !== '' ? $desdeIngresado : $porDefecto['fecha_desde'];
        $hasta = $hastaIngresado !== '' ? $hastaIngresado : $porDefecto['fecha_hasta'];

        $error = '';

        if (!$this->esFechaValida($desde) || !$this->esFechaValida($hasta)) {
            $error = self::ERROR_FECHA_FORMATO;
        } elseif ($desde > $hasta) {
            $error = self::ERROR_FECHA_ORDEN; // "Y-m-d" se ordena bien como texto
        }

        $filtros = ['fecha_desde' => $desde, 'fecha_hasta' => $hasta];

        $estado = (string) ($_GET['estado'] ?? '');

        if (in_array($estado, self::ESTADOS, true)) {
            $filtros['estado'] = $estado;
        }

        $tipo = (string) ($_GET['tipo'] ?? '');

        if ($tipo !== '' && ctype_digit($tipo)) {
            $filtros['id_tipo_pedido'] = (int) $tipo;
        }

        $cliente = trim((string) ($_GET['cliente'] ?? ''));

        if ($cliente !== '') {
            $filtros['cliente_nombre'] = $cliente;
        }

        return [
            'filtros' => $filtros,
            'error'   => $error,
            'desde'   => $error !== '' ? $desdeIngresado : $desde,
            'hasta'   => $error !== '' ? $hastaIngresado : $hasta,
        ];
    }

    /**
     * "2026-09-20", con el formato exacto (evita que "2026-9-2" o "2026-13-45"
     * se cuelen en la consulta SQL).
     */
    private function esFechaValida(string $fecha): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $dt !== false && $dt->format('Y-m-d') === $fecha;
    }

    /**
     * Query string de los filtros ya validados, sin "pagina": la usan el botón
     * de exportar y los links de paginación de la vista.
     *
     * @param array<string, int|string> $filtros
     */
    private function queryDeFiltros(array $filtros): string
    {
        $query = [
            'cliente'     => $filtros['cliente_nombre'] ?? '',
            'tipo'        => isset($filtros['id_tipo_pedido']) ? (string) $filtros['id_tipo_pedido'] : '',
            'estado'      => $filtros['estado'] ?? '',
            'fecha_desde' => $filtros['fecha_desde'],
            'fecha_hasta' => $filtros['fecha_hasta'],
        ];

        return http_build_query(array_filter($query, static fn ($valor): bool => $valor !== ''));
    }

    /**
     * Fila "Filtros: ..." del CSV: el rango siempre, y estado, tipo y cliente
     * solo si están aplicados.
     *
     * @param array<string, int|string> $filtros
     */
    private function textoDeFiltros(array $filtros): string
    {
        $partes = [sprintf(
            'Entrega del %s al %s (incluye atrasados)',
            (new \DateTime((string) $filtros['fecha_desde']))->format('d/m/Y'),
            (new \DateTime((string) $filtros['fecha_hasta']))->format('d/m/Y')
        )];

        if (isset($filtros['estado'])) {
            $partes[] = 'Estado: ' . $filtros['estado'];
        }

        if (isset($filtros['id_tipo_pedido'])) {
            $tipo     = TipoPedido::buscarPorId((int) $filtros['id_tipo_pedido']);
            $partes[] = 'Tipo: ' . ($tipo['nombre'] ?? (string) $filtros['id_tipo_pedido']);
        }

        if (isset($filtros['cliente_nombre'])) {
            $partes[] = 'Cliente: ' . $filtros['cliente_nombre'];
        }

        return 'Filtros: ' . implode('; ', $partes);
    }
}
