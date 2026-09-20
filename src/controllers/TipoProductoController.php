<?php

declare(strict_types=1);

/**
 * BakerSoft - ABM de tipos de producto (HU-05 a HU-08).
 *
 * En la interfaz la entidad se muestra como "Categoría".
 * Las bajas son lógicas: toggleEstado() apaga o prende `activo`, no borra.
 */
final class TipoProductoController
{
    private const ERROR_DUPLICADO = 'El código o nombre ya existen.';

    private const ERROR_CODIGO_FIJO = 'El código no puede modificarse.';

    /**
     * GET /tipo-producto
     */
    public function index(): void
    {
        requireAuth();
        requireRole('tipo-producto');

        $buscar = trim((string) ($_GET['buscar'] ?? ''));
        $estado = (string) ($_GET['estado'] ?? '');

        // Cualquier valor raro en la query se trata como "todos".
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            $estado = '';
        }

        view('layouts/app-layout', [
            'slot'       => 'tipo-producto/index',
            'titulo'     => 'Tipos de producto',
            'menuActivo' => 'tipo-producto',
            'tipos'      => TipoProducto::listar($buscar, $estado !== '' ? $estado : null),
            'buscar'     => $buscar,
            'estado'     => $estado,
        ]);
    }

    /**
     * GET /tipo-producto/nuevo
     *
     * @param array<string, mixed> $datos
     */
    public function showCreateForm(array $datos = []): void
    {
        requireAuth();
        requireRole('tipo-producto');

        view('layouts/app-layout', array_merge([
            'slot'       => 'tipo-producto/form',
            'titulo'     => 'Nuevo tipo de producto',
            'menuActivo' => 'tipo-producto',
            'modo'       => 'crear',
            'tipo'       => ['id_tipo_producto' => null, 'codigo' => '', 'nombre' => '', 'descripcion' => ''],
            'error'      => '',
            'errores'    => [],
        ], $datos));
    }

    /**
     * POST /tipo-producto/nuevo
     */
    public function store(): void
    {
        requireAuth();
        requireRole('tipo-producto');

        $codigo      = trim((string) ($_POST['codigo'] ?? ''));
        $nombre      = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        $errores = [];

        if ($codigo === '') {
            $errores['codigo'] = 'Este campo es obligatorio';
        } elseif (mb_strlen($codigo) > 30) {
            $errores['codigo'] = 'No puede superar los 30 caracteres';
        } elseif (!ctype_digit($codigo)) {
            $errores['codigo'] = 'El código debe ser numérico';
        }

        if ($nombre === '') {
            $errores['nombre'] = 'Este campo es obligatorio';
        } elseif (mb_strlen($nombre) > 80) {
            $errores['nombre'] = 'No puede superar los 80 caracteres';
        }

        if (mb_strlen($descripcion) > 255) {
            $errores['descripcion'] = 'No puede superar los 255 caracteres';
        }

        $duplicado = $errores === []
            && (TipoProducto::existeCodigo($codigo) || TipoProducto::existeNombre($nombre));

        if ($errores !== [] || $duplicado) {
            $this->showCreateForm([
                'error'   => $duplicado ? self::ERROR_DUPLICADO : '',
                'errores' => $errores,
                'tipo'    => [
                    'id_tipo_producto' => null,
                    'codigo'           => $codigo,
                    'nombre'           => $nombre,
                    'descripcion'      => $descripcion,
                ],
            ]);
            return;
        }

        try {
            $id = TipoProducto::crear([
                'codigo'      => $codigo,
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
            ]);
        } catch (PDOException $e) {
            // 23000 = índice único. Cubre el caso de dos altas simultáneas
            // entre la verificación de arriba y el INSERT.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $this->showCreateForm([
                'error' => self::ERROR_DUPLICADO,
                'tipo'  => [
                    'id_tipo_producto' => null,
                    'codigo'           => $codigo,
                    'nombre'           => $nombre,
                    'descripcion'      => $descripcion,
                ],
            ]);
            return;
        }

        app_log(sprintf('TIPO PRODUCTO ALTA id=%d codigo=%s email=%s', $id, $codigo, $this->emailActual()));

        flash_set('El tipo de producto se creó correctamente.', 'ok');
        redirect('tipo-producto');
    }

    /**
     * GET /tipo-producto/{id}/editar
     *
     * @param array<string, mixed> $datos
     */
    public function showEditForm(int $id, array $datos = []): void
    {
        requireAuth();
        requireRole('tipo-producto');

        $tipo = TipoProducto::buscarPorId($id);

        if ($tipo === null) {
            $this->noEncontrado();
            return;
        }

        view('layouts/app-layout', array_merge([
            'slot'       => 'tipo-producto/form',
            'titulo'     => 'Editar tipo de producto',
            'menuActivo' => 'tipo-producto',
            'modo'       => 'editar',
            'tipo'       => $tipo,
            'error'      => '',
            'errores'    => [],
        ], $datos));
    }

    /**
     * POST /tipo-producto/{id}/editar
     */
    public function update(int $id): void
    {
        requireAuth();
        requireRole('tipo-producto');

        $tipo = TipoProducto::buscarPorId($id);

        if ($tipo === null) {
            $this->noEncontrado();
            return;
        }

        $nombre      = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        // El campo código va deshabilitado en el formulario, así que normalmente
        // no llega. Si llega y es distinto al guardado, alguien lo está forzando.
        $codigoEnviado = isset($_POST['codigo']) ? trim((string) $_POST['codigo']) : null;

        $errores = [];
        $error   = '';

        if ($codigoEnviado !== null && $codigoEnviado !== '' && $codigoEnviado !== $tipo['codigo']) {
            $error = self::ERROR_CODIGO_FIJO;
        }

        if ($nombre === '') {
            $errores['nombre'] = 'Este campo es obligatorio';
        } elseif (mb_strlen($nombre) > 80) {
            $errores['nombre'] = 'No puede superar los 80 caracteres';
        }

        if (mb_strlen($descripcion) > 255) {
            $errores['descripcion'] = 'No puede superar los 255 caracteres';
        }

        if ($error === '' && $errores === [] && TipoProducto::existeNombre($nombre, $id)) {
            $error = self::ERROR_DUPLICADO;
        }

        if ($error !== '' || $errores !== []) {
            $this->showEditForm($id, [
                'error'   => $error,
                'errores' => $errores,
                // El código que se muestra es siempre el guardado, nunca el enviado.
                'tipo'    => array_merge($tipo, [
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion,
                ]),
            ]);
            return;
        }

        TipoProducto::actualizar($id, ['nombre' => $nombre, 'descripcion' => $descripcion]);

        app_log(sprintf('TIPO PRODUCTO EDICION id=%d codigo=%s email=%s', $id, $tipo['codigo'], $this->emailActual()));

        flash_set('Los cambios se guardaron.', 'ok');
        redirect('tipo-producto');
    }

    /**
     * POST /tipo-producto/{id}/estado
     *
     * Baja lógica: alterna `activo`. NO borra el registro.
     *
     * Al desactivar una categoría con productos activos no hay cascada (los
     * productos no cambian de estado), pero se avisa antes de aplicar el
     * cambio para que la baja sea una decisión informada (HT-03). Si el
     * usuario ya confirmó (viene `confirmar=1` en el POST), se procede directo.
     */
    public function toggleEstado(int $id): void
    {
        requireAuth();
        requireRole('tipo-producto');

        $tipo = TipoProducto::buscarPorId($id);

        if ($tipo === null) {
            $this->noEncontrado();
            return;
        }

        $nuevoEstado = !((bool) $tipo['activo']);
        $confirmado  = ($_POST['confirmar'] ?? '') === '1';

        if (!$nuevoEstado && !$confirmado) {
            $activos = Producto::contarActivosPorTipo($id);

            if ($activos > 0) {
                view('layouts/app-layout', [
                    'slot'            => 'tipo-producto/confirmar-baja',
                    'titulo'          => 'Confirmar baja',
                    'menuActivo'      => 'tipo-producto',
                    'tipo'            => $tipo,
                    'cantidadActivos' => $activos,
                ]);
                return;
            }
        }

        TipoProducto::cambiarEstado($id, $nuevoEstado);

        app_log(sprintf(
            'TIPO PRODUCTO ESTADO id=%d codigo=%s activo=%s email=%s',
            $id,
            $tipo['codigo'],
            $nuevoEstado ? '1' : '0',
            $this->emailActual()
        ));

        flash_set(
            $nuevoEstado
                ? sprintf('"%s" quedó activo.', $tipo['nombre'])
                : sprintf('"%s" quedó inactivo.', $tipo['nombre']),
            'ok'
        );

        redirect('tipo-producto');
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
