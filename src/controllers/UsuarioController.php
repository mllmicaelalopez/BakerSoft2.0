<?php

declare(strict_types=1);

/**
 * BakerSoft - ABM mínima de usuarios (HT-01: Roles y Permisos).
 *
 * El alta ya existe vía el registro público (/register), así que acá no hay
 * alta manual ni edición de contraseña: solo listar, cambiar el rol y
 * activar/desactivar la cuenta (baja lógica, igual que Producto y Tipo de
 * Producto: nunca se borra el registro).
 */
final class UsuarioController
{
    /**
     * GET /usuario
     */
    public function index(): void
    {
        requireAuth();
        requireRole('usuario');

        $buscar = trim((string) ($_GET['buscar'] ?? ''));
        $estado = (string) ($_GET['estado'] ?? '');

        // Cualquier valor raro en la query se trata como "todos".
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            $estado = '';
        }

        view('layouts/app-layout', [
            'slot'       => 'usuario/index',
            'titulo'     => 'Usuarios',
            'menuActivo' => 'usuario',
            'usuarios'   => Usuario::listar($buscar, $estado !== '' ? $estado : null),
            'buscar'     => $buscar,
            'estado'     => $estado,
        ]);
    }

    /**
     * GET /usuario/nuevo, POST /usuario/nuevo
     *
     * No hay alta manual en este ABM: las cuentas se crean por el registro
     * público (/register). Estos métodos solo existen para que
     * despachar_abm() tenga algo que llamar si alguien entra a la ruta; no
     * hay ningún link a ella desde la interfaz.
     */
    public function showCreateForm(array $datos = []): void
    {
        requireAuth();
        requireRole('usuario');

        redirect('usuario');
    }

    public function store(): void
    {
        requireAuth();
        requireRole('usuario');

        redirect('usuario');
    }

    /**
     * GET /usuario/{id}/editar
     *
     * @param array<string, mixed> $datos
     */
    public function showEditForm(int $id, array $datos = []): void
    {
        requireAuth();
        requireRole('usuario');

        $usuario = Usuario::buscarPorId($id);

        if ($usuario === null) {
            $this->noEncontrado();
            return;
        }

        // OJO: la clave se llama "cuenta" y no "usuario" a propósito. app-layout
        // usa la variable $usuario para el usuario logueado (sidebar); si esta
        // vista pasara "usuario" pisaría esos datos y rompería el sidebar.
        view('layouts/app-layout', array_merge([
            'slot'       => 'usuario/form',
            'titulo'     => 'Editar usuario',
            'menuActivo' => 'usuario',
            'cuenta'     => $usuario,
            'roles'      => Rol::listar(),
            'error'      => '',
            'errores'    => [],
        ], $datos));
    }

    /**
     * POST /usuario/{id}/editar
     *
     * Solo cambia el rol. La contraseña y el resto de la cuenta no se tocan
     * desde acá.
     */
    public function update(int $id): void
    {
        requireAuth();
        requireRole('usuario');

        $usuario = Usuario::buscarPorId($id);

        if ($usuario === null) {
            $this->noEncontrado();
            return;
        }

        $idRol   = trim((string) ($_POST['id_rol'] ?? ''));
        $errores = [];

        if ($idRol === '' || !ctype_digit($idRol) || Rol::buscarPorId((int) $idRol) === null) {
            $errores['id_rol'] = 'Elegí un rol válido';
        }

        if ($errores !== []) {
            $this->showEditForm($id, [
                'errores' => $errores,
                'roles'   => Rol::listar(),
            ]);
            return;
        }

        Usuario::actualizarRol($id, (int) $idRol);

        app_log(sprintf(
            'USUARIO ROL id=%d email=%s rol=%s email_admin=%s',
            $id,
            $usuario['email'],
            $idRol,
            $this->emailActual()
        ));

        flash_set('El rol se actualizó.', 'ok');
        redirect('usuario');
    }

    /**
     * POST /usuario/{id}/estado
     *
     * Baja lógica: alterna `activo`. NO borra la cuenta.
     *
     * Un administrador no puede desactivar su propia cuenta desde acá: sin
     * este freno, un solo click dejaría a todo el sistema sin nadie que
     * pueda reactivarla.
     */
    public function toggleEstado(int $id): void
    {
        requireAuth();
        requireRole('usuario');

        $usuario = Usuario::buscarPorId($id);

        if ($usuario === null) {
            $this->noEncontrado();
            return;
        }

        if ($id === (int) (usuario_actual()['id'] ?? 0)) {
            flash_set('No podés desactivar tu propia cuenta.', 'error');
            redirect('usuario');
        }

        $nuevoEstado = !((bool) $usuario['activo']);

        Usuario::cambiarEstado($id, $nuevoEstado);

        app_log(sprintf(
            'USUARIO ESTADO id=%d email=%s activo=%s email_admin=%s',
            $id,
            $usuario['email'],
            $nuevoEstado ? '1' : '0',
            $this->emailActual()
        ));

        flash_set(
            sprintf('"%s" quedó %s.', $usuario['nombre_usuario'], $nuevoEstado ? 'activo' : 'inactivo'),
            'ok'
        );

        redirect('usuario');
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
