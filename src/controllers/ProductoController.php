<?php

declare(strict_types=1);

/**
 * BakerSoft - ABM de productos (HU-01 a HU-04 de Sprint 2).
 *
 * Las bajas son lógicas: toggleEstado() apaga o prende `activo`, no borra.
 */
final class ProductoController
{
    private const ERROR_DUPLICADO = 'El código o nombre ya existen.';

    private const ERROR_CODIGO_FIJO = 'El código no puede modificarse.';

    /** Tope de precio que entra en un DECIMAL(10,2). */
    private const PRECIO_MAXIMO = 99999999.99;

    /**
     * GET /producto
     */
    public function index(): void
    {
        requireAuth();
        requireRole('producto');

        $buscar = trim((string) ($_GET['buscar'] ?? ''));
        $estado = (string) ($_GET['estado'] ?? '');

        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            $estado = '';
        }

        $categoria = (string) ($_GET['categoria'] ?? '');
        $idTipo    = ctype_digit($categoria) ? (int) $categoria : null;

        view('layouts/app-layout', [
            'slot'       => 'producto/index',
            'titulo'     => 'Productos',
            'menuActivo' => 'producto',
            'productos'  => Producto::listar($buscar, $estado !== '' ? $estado : null, $idTipo),
            'categorias' => TipoProducto::listar(),
            'buscar'     => $buscar,
            'estado'     => $estado,
            'categoria'  => $idTipo,
        ]);
    }

    /**
     * GET /producto/nuevo
     *
     * @param array<string, mixed> $datos
     */
    public function showCreateForm(array $datos = []): void
    {
        requireAuth();
        requireRole('producto');

        view('layouts/app-layout', array_merge([
            'slot'       => 'producto/form',
            'titulo'     => 'Nuevo producto',
            'menuActivo' => 'producto',
            'modo'       => 'crear',
            'producto'   => [
                'id_producto'      => null,
                'codigo'           => '',
                'id_tipo_producto' => '',
                'nombre'           => '',
                'precio'           => '',
                'descripcion'      => '',
                'activo'           => 1,
            ],
            // En el alta solo se ofrecen categorías activas.
            'categorias' => TipoProducto::listar(null, 'activo'),
            'error'      => '',
            'errores'    => [],
        ], $datos));
    }

    /**
     * POST /producto/nuevo
     */
    public function store(): void
    {
        requireAuth();
        requireRole('producto');

        $campos  = $this->camposDelPost();
        $errores = $this->validar($campos, null);

        if ($campos['codigo'] === '') {
            $errores['codigo'] = 'Este campo es obligatorio';
        } elseif (mb_strlen($campos['codigo']) > 30) {
            $errores['codigo'] = 'No puede superar los 30 caracteres';
        }

        $duplicado = $errores === []
            && (Producto::existeCodigo($campos['codigo']) || Producto::existeNombre($campos['nombre']));

        if ($errores !== [] || $duplicado) {
            $this->showCreateForm([
                'error'    => $duplicado ? self::ERROR_DUPLICADO : '',
                'errores'  => $errores,
                'producto' => $this->productoDesdeCampos($campos, null),
            ]);
            return;
        }

        try {
            $id = Producto::crear([
                'codigo'           => $campos['codigo'],
                'id_tipo_producto' => (int) $campos['id_tipo_producto'],
                'nombre'           => $campos['nombre'],
                'precio'           => $campos['precio'],
                'descripcion'      => $campos['descripcion'],
            ]);
        } catch (PDOException $e) {
            // 23000 = índice único; cubre dos altas simultáneas.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $this->showCreateForm([
                'error'    => self::ERROR_DUPLICADO,
                'producto' => $this->productoDesdeCampos($campos, null),
            ]);
            return;
        }

        app_log(sprintf('PRODUCTO ALTA id=%d codigo=%s email=%s', $id, $campos['codigo'], $this->emailActual()));

        flash_set('El producto se creó correctamente.', 'ok');
        redirect('producto');
    }

    /**
     * GET /producto/{id}/editar
     *
     * @param array<string, mixed> $datos
     */
    public function showEditForm(int $id, array $datos = []): void
    {
        requireAuth();
        requireRole('producto');

        $producto = Producto::buscarPorId($id);

        if ($producto === null) {
            $this->noEncontrado();
            return;
        }

        view('layouts/app-layout', array_merge([
            'slot'       => 'producto/form',
            'titulo'     => 'Editar producto',
            'menuActivo' => 'producto',
            'modo'       => 'editar',
            'producto'   => $producto,
            'categorias' => $this->categoriasParaEdicion((int) $producto['id_tipo_producto']),
            'error'      => '',
            'errores'    => [],
        ], $datos));
    }

    /**
     * POST /producto/{id}/editar
     */
    public function update(int $id): void
    {
        requireAuth();
        requireRole('producto');

        $producto = Producto::buscarPorId($id);

        if ($producto === null) {
            $this->noEncontrado();
            return;
        }

        $campos  = $this->camposDelPost();
        $errores = $this->validar($campos, $id);
        $error   = '';

        // El campo código va deshabilitado en el formulario, así que no debería
        // llegar. Si llega y es distinto al guardado, alguien lo está forzando.
        $codigoEnviado = isset($_POST['codigo']) ? trim((string) $_POST['codigo']) : null;

        if ($codigoEnviado !== null && $codigoEnviado !== '' && $codigoEnviado !== $producto['codigo']) {
            $error = self::ERROR_CODIGO_FIJO;
        }

        if ($error === '' && $errores === [] && Producto::existeNombre($campos['nombre'], $id)) {
            $error = self::ERROR_DUPLICADO;
        }

        if ($error !== '' || $errores !== []) {
            $this->showEditForm($id, [
                'error'   => $error,
                'errores' => $errores,
                // El código mostrado es siempre el guardado, nunca el enviado.
                'producto' => array_merge($producto, [
                    'id_tipo_producto' => $campos['id_tipo_producto'],
                    'nombre'           => $campos['nombre'],
                    'precio'           => $campos['precio'],
                    'descripcion'      => $campos['descripcion'],
                ]),
            ]);
            return;
        }

        Producto::actualizar($id, [
            'id_tipo_producto' => (int) $campos['id_tipo_producto'],
            'nombre'           => $campos['nombre'],
            'precio'           => $campos['precio'],
            'descripcion'      => $campos['descripcion'],
        ]);

        app_log(sprintf('PRODUCTO EDICION id=%d codigo=%s email=%s', $id, $producto['codigo'], $this->emailActual()));

        flash_set('Los cambios se guardaron.', 'ok');
        redirect('producto');
    }

    /**
     * POST /producto/{id}/estado
     *
     * Baja lógica: alterna `activo`. NO borra el registro.
     */
    public function toggleEstado(int $id): void
    {
        requireAuth();
        requireRole('producto');

        $producto = Producto::buscarPorId($id);

        if ($producto === null) {
            $this->noEncontrado();
            return;
        }

        $nuevoEstado = !((bool) $producto['activo']);

        Producto::cambiarEstado($id, $nuevoEstado);

        app_log(sprintf(
            'PRODUCTO ESTADO id=%d codigo=%s activo=%s email=%s',
            $id,
            $producto['codigo'],
            $nuevoEstado ? '1' : '0',
            $this->emailActual()
        ));

        flash_set(
            sprintf('"%s" quedó %s.', $producto['nombre'], $nuevoEstado ? 'activo' : 'inactivo'),
            'ok'
        );

        redirect('producto');
    }

    /* ----------------------------------------------------------------------- */

    /**
     * @return array{codigo: string, id_tipo_producto: string, nombre: string, precio: string, descripcion: string}
     */
    private function camposDelPost(): array
    {
        return [
            'codigo'           => trim((string) ($_POST['codigo'] ?? '')),
            'id_tipo_producto' => trim((string) ($_POST['id_tipo_producto'] ?? '')),
            'nombre'           => trim((string) ($_POST['nombre'] ?? '')),
            // La coma decimal se normaliza acá, en la entrada, para que el
            // valor que se valida sea exactamente el que va a la base. MySQL
            // rechaza "1850,50" en una columna DECIMAL.
            'precio'           => str_replace(',', '.', trim((string) ($_POST['precio'] ?? ''))),
            'descripcion'      => trim((string) ($_POST['descripcion'] ?? '')),
        ];
    }

    /**
     * Validaciones comunes al alta y a la edición (el código se valida aparte,
     * porque en la edición no se toca).
     *
     * @param array<string, string> $campos
     * @return array<string, string>
     */
    private function validar(array $campos, ?int $id): array
    {
        $errores = [];

        if ($campos['nombre'] === '') {
            $errores['nombre'] = 'Este campo es obligatorio';
        } elseif (mb_strlen($campos['nombre']) > 120) {
            $errores['nombre'] = 'No puede superar los 120 caracteres';
        }

        if ($campos['id_tipo_producto'] === '') {
            $errores['id_tipo_producto'] = 'Elegí una categoría';
        } elseif (!ctype_digit($campos['id_tipo_producto'])
            || TipoProducto::buscarPorId((int) $campos['id_tipo_producto']) === null) {
            $errores['id_tipo_producto'] = 'La categoría seleccionada no existe';
        }

        // Llega ya normalizado desde camposDelPost().
        if ($campos['precio'] === '') {
            $errores['precio'] = 'Este campo es obligatorio';
        } elseif (!is_numeric($campos['precio'])) {
            $errores['precio'] = 'Ingresá un número válido';
        } elseif ((float) $campos['precio'] <= 0) {
            $errores['precio'] = 'El precio debe ser mayor a cero';
        } elseif ((float) $campos['precio'] > self::PRECIO_MAXIMO) {
            $errores['precio'] = 'El precio es demasiado alto';
        }

        if (mb_strlen($campos['descripcion']) > 255) {
            $errores['descripcion'] = 'No puede superar los 255 caracteres';
        }

        return $errores;
    }

    /**
     * Categorías del select en edición: las activas, más la del propio producto
     * si quedó inactiva. Sin esto, editar un producto de una categoría dada de
     * baja le cambiaría la categoría sin que nadie lo pida.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoriasParaEdicion(int $idActual): array
    {
        $categorias = TipoProducto::listar(null, 'activo');

        foreach ($categorias as $categoria) {
            if ((int) $categoria['id_tipo_producto'] === $idActual) {
                return $categorias;
            }
        }

        $actual = TipoProducto::buscarPorId($idActual);

        if ($actual !== null) {
            $categorias[] = $actual;
        }

        return $categorias;
    }

    /**
     * @param array<string, string> $campos
     * @return array<string, mixed>
     */
    private function productoDesdeCampos(array $campos, ?int $id): array
    {
        return [
            'id_producto'      => $id,
            'codigo'           => $campos['codigo'],
            'id_tipo_producto' => $campos['id_tipo_producto'],
            'nombre'           => $campos['nombre'],
            'precio'           => $campos['precio'],
            'descripcion'      => $campos['descripcion'],
            'activo'           => 1,
        ];
    }

    private function noEncontrado(): void
    {
        http_response_code(404);
        view('errors/404', ['route' => current_route()]);
    }

    private function emailActual(): string
    {
        return usuario_actual()['email'] ?? '';
    }
}
