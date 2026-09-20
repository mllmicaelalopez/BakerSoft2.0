<?php

declare(strict_types=1);

/**
 * BakerSoft - ABM de pedidos.
 *
 * HU-09 (alta), HU-10 (modificación: productos, cantidades y fecha de
 * entrega; cliente y tipo de pedido quedan fijos), HU-11 (consulta: listado
 * con filtros) y HU-12 (baja lógica / cancelación) ya están. No hay una
 * pantalla de "detalle" separada: showEditForm() cumple ese rol (de solo
 * lectura cuando el pedido no es editable) y ahí mismo vive el botón
 * "Cancelar pedido".
 *
 * Permisos: index() y showEditForm() (listado y detalle) usan
 * requireRole('pedido-consulta') — los cuatro roles pueden consultar, aunque
 * showEditForm() solo deja editar/cancelar de verdad a administrador y
 * cajero (ver esEditable() y esCancelable(): maestro_panadero y repartidor
 * siempre ven el detalle en solo lectura, sin el botón de cancelar).
 * showCreateForm()/store()/update()/toggleEstado() usan requireRole('pedido')
 * — solo administrador y cajero pueden dar de alta, modificar o cancelar. El
 * cajero, además, solo puede cancelar sus propios pedidos dentro de los 30
 * minutos de creados (puedeCancelar()); esa restricción más fina no la
 * resuelve requireRole() y se chequea a mano en toggleEstado(). Ver
 * src/config/permisos.php.
 */
final class PedidoController
{
    private const ERROR_GENERICO = 'Revisá los datos marcados.';

    private const MOTIVO_SOLO_LECTURA = 'El pedido no puede modificarse en su estado actual.';

    private const MOTIVO_NO_CANCELABLE = 'El pedido no puede cancelarse en su estado actual.';

    /** Estados en los que cualquiera con permiso de módulo puede modificar. */
    private const ESTADOS_EDITABLES = ['Nuevo', 'Confirmado'];

    /** Estado en el que solo el administrador puede modificar. */
    private const ESTADO_EDITABLE_SOLO_ADMIN = 'En preparación';

    /** Todos los valores posibles de pedido.estado (columna ENUM, migración 008). */
    private const ESTADOS = ['Nuevo', 'Confirmado', 'En preparación', 'Listo', 'Entregado', 'Cancelado'];

    /** Estados desde los que un pedido todavía se puede cancelar (HU-12). */
    private const ESTADOS_CANCELABLES = ['Nuevo', 'Confirmado', 'En preparación'];

    /** Minutos que tiene el cajero, desde que creó el pedido, para poder cancelarlo él mismo. */
    private const VENTANA_CANCELACION_CAJERO_MINUTOS = 30;

    /** Motivos predefinidos del select de cancelación; "Otro" habilita la aclaración obligatoria. */
    private const MOTIVOS_CANCELACION = [
        'Cliente canceló el pedido',
        'Falta de stock o insumos',
        'Error en la carga del pedido',
        'Pedido duplicado',
        'Otro',
    ];

    /**
     * GET /pedido
     *
     * Listado con filtros por cliente, tipo de pedido, estado y rango de
     * fecha de entrega (HU-11), aplicados en la consulta SQL (Pedido::listar()),
     * no en PHP después de traer todo.
     *
     * Los filtros se aplican por fetch sin recargar la página completa: si la
     * solicitud trae el header X-Requested-With: XMLHttpRequest (lo manda el
     * fetch de assets/js/app.js), se devuelve solo el fragmento de la tabla
     * (pedido/_tabla.php), sin el layout. Si no (entrar directo a /pedido,
     * recargar), se sirve la vista completa como siempre.
     */
    public function index(): void
    {
        requireAuth();
        requireRole('pedido-consulta');

        $filtros = $this->filtrosDesdeQuery();

        $datos = [
            'pedidos'       => Pedido::listar($filtros),
            'tiposPedido'   => TipoPedido::listar(),
            'estados'       => self::ESTADOS,
            'filtroCliente' => $filtros['cliente_nombre'] ?? '',
            'filtroTipo'    => isset($filtros['id_tipo_pedido']) ? (string) $filtros['id_tipo_pedido'] : '',
            'filtroEstado'  => $filtros['estado'] ?? '',
            'filtroDesde'   => $filtros['fecha_desde'] ?? '',
            'filtroHasta'   => $filtros['fecha_hasta'] ?? '',
        ];

        if ($this->esSolicitudFetch()) {
            view('pedido/_tabla', $datos);
            return;
        }

        view('layouts/app-layout', array_merge($datos, [
            'slot'       => 'pedido/index',
            'titulo'     => 'Pedidos',
            'menuActivo' => 'pedido',
        ]));
    }

    /**
     * GET /pedido/nuevo
     *
     * @param array<string, mixed> $datos
     */
    public function showCreateForm(array $datos = []): void
    {
        requireAuth();
        requireRole('pedido');

        view('layouts/app-layout', array_merge([
            'slot'                     => 'pedido/form',
            'titulo'                   => 'Nuevo pedido',
            'menuActivo'               => 'pedido',
            'modo'                     => 'crear',
            'pedido'                   => null,
            'tiposPedido'              => TipoPedido::listar(),
            'clientes'                 => Cliente::listar(),
            'productosDisponibles'     => Producto::listar(null, 'activo'),
            'modoCliente'              => 'existente',
            'idCliente'                => '',
            'clienteNuevo'             => ['nombre_razon_social' => '', 'tipo_cliente' => '', 'cuit_cuil' => '', 'telefono' => '', 'email' => '', 'direccion' => ''],
            'idTipoPedido'             => '',
            'fechaEntrega'             => '',
            'productos'                => [['id_producto' => '', 'cantidad' => '']],
            'anticipacionInsuficiente' => false,
            'confirmarFueraPlazo'      => false,
            'soloLectura'              => false,
            'motivoSoloLectura'        => '',
            'advertenciaEnPreparacion' => false,
            'puedeCancelar'            => false,
            'mostrarCancelacion'       => false,
            'motivosCancelacion'       => self::MOTIVOS_CANCELACION,
            'motivoCancelacion'        => '',
            'motivoAclaracion'         => '',
            'error'                    => '',
            'errores'                  => [],
        ], $datos));
    }

    /**
     * POST /pedido/nuevo
     */
    public function store(): void
    {
        requireAuth();
        requireRole('pedido');

        $campos  = $this->camposDelPost();
        $errores = $this->validar($campos);

        // La anticipación mínima se revisa aparte: si no alcanza y todavía no
        // se confirmó explícitamente, no es un error de campo sino un aviso
        // con la opción de confirmar igual (HU-09).
        if ($errores === []) {
            $tipoPedido = TipoPedido::buscarPorId((int) $campos['id_tipo_pedido']);
            $anticipacion = $this->revisarAnticipacion($tipoPedido, $campos['fechaEntregaDt']);

            if ($anticipacion === 'vencida') {
                $errores['fecha_entrega'] = 'La fecha de entrega no puede ser anterior a este momento.';
            } elseif ($anticipacion === 'insuficiente' && !$campos['confirmarFueraPlazo']) {
                http_response_code(400);
                $this->showCreateForm(array_merge($this->datosParaRedisplay($campos), [
                    'anticipacionInsuficiente' => true,
                ]));
                return;
            }
        }

        if ($errores !== []) {
            http_response_code(400);
            $this->showCreateForm(array_merge($this->datosParaRedisplay($campos), [
                'error'   => self::ERROR_GENERICO,
                'errores' => $errores,
            ]));
            return;
        }

        $fueraDePlazo = $campos['confirmarFueraPlazo']
            && $this->revisarAnticipacion(TipoPedido::buscarPorId((int) $campos['id_tipo_pedido']), $campos['fechaEntregaDt']) === 'insuficiente';

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            if ($campos['modoCliente'] === 'nuevo') {
                $idCliente = Cliente::crear([
                    'nombre_razon_social' => $campos['clienteNuevo']['nombre_razon_social'],
                    'tipo_cliente'        => $campos['clienteNuevo']['tipo_cliente'],
                    'cuit_cuil'           => $campos['clienteNuevo']['cuit_cuil'],
                    'telefono'            => $campos['clienteNuevo']['telefono'],
                    'email'               => $campos['clienteNuevo']['email'],
                    'direccion'           => $campos['clienteNuevo']['direccion'],
                ]);
            } else {
                $idCliente = (int) $campos['idCliente'];
            }

            $idPedido = Pedido::crear([
                'id_cliente'                          => $idCliente,
                'id_tipo_pedido'                       => (int) $campos['id_tipo_pedido'],
                'usuario_registro'                    => (int) usuario_actual()['id'],
                'fecha_entrega'                        => $campos['fechaEntregaDt']->format('Y-m-d H:i:s'),
                'confirmado_fuera_de_anticipacion'    => $fueraDePlazo,
                'fecha_hora_confirmacion_fuera_plazo' => $fueraDePlazo ? date('Y-m-d H:i:s') : null,
            ]);

            foreach ($campos['productos'] as $item) {
                DetallePedido::crear($idPedido, (int) $item['id_producto'], $item['cantidad']);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        app_log(sprintf(
            'PEDIDO ALTA id=%d cliente_id=%d tipo_pedido_id=%d fuera_de_plazo=%s email=%s',
            $idPedido,
            $idCliente,
            (int) $campos['id_tipo_pedido'],
            $fueraDePlazo ? '1' : '0',
            $this->emailActual()
        ));

        flash_set(sprintf('El pedido #%d se creó correctamente, en estado "Nuevo".', $idPedido), 'ok');
        redirect('pedido');
    }

    /**
     * GET /pedido/{id}/editar
     *
     * También la usa update() para volver a mostrar el formulario tras un
     * error o un rechazo por estado (mismo criterio que showCreateForm() en
     * store()): $datos pisa lo que se cargó de la base.
     *
     * @param array<string, mixed> $datos
     */
    public function showEditForm(int $id, array $datos = []): void
    {
        requireAuth();
        requireRole('pedido-consulta');

        $pedido = Pedido::buscarPorId($id);

        if ($pedido === null) {
            $this->noEncontrado();
            return;
        }

        $rol       = (string) (usuario_actual()['rol'] ?? '');
        $idUsuario = (int) usuario_actual()['id'];
        $editable  = $this->esEditable((string) $pedido['estado'], $rol);

        view('layouts/app-layout', array_merge([
            'slot'                     => 'pedido/form',
            'titulo'                   => sprintf('Pedido #%d', $id),
            'menuActivo'               => 'pedido',
            'modo'                     => 'editar',
            'pedido'                   => $pedido,
            'tiposPedido'              => TipoPedido::listar(),
            'clientes'                 => Cliente::listar(),
            'productosDisponibles'     => Producto::listar(null, 'activo'),
            'modoCliente'              => 'existente',
            'idCliente'                => (string) $pedido['id_cliente'],
            'clienteNuevo'             => ['nombre_razon_social' => '', 'tipo_cliente' => '', 'cuit_cuil' => '', 'telefono' => '', 'email' => '', 'direccion' => ''],
            'idTipoPedido'             => (string) $pedido['id_tipo_pedido'],
            'fechaEntrega'             => $this->formatearFechaLocal((string) $pedido['fecha_entrega']),
            'productos'                => $this->detalleParaFormulario($id),
            'anticipacionInsuficiente' => false,
            'confirmarFueraPlazo'      => false,
            'soloLectura'              => !$editable,
            'motivoSoloLectura'        => !$editable ? self::MOTIVO_SOLO_LECTURA : '',
            'advertenciaEnPreparacion' => $editable && $pedido['estado'] === self::ESTADO_EDITABLE_SOLO_ADMIN,
            // La cancelación es una capacidad independiente de la edición de
            // productos/fecha: un pedido "En preparación" puede estar
            // soloLectura para un cajero (no puede tocar productos/fecha) y
            // seguir siendo cancelable para ese mismo cajero (pedido propio,
            // dentro de los 30 minutos).
            'puedeCancelar'            => $this->esCancelable($pedido, $rol, $idUsuario),
            'mostrarCancelacion'       => false,
            'motivosCancelacion'       => self::MOTIVOS_CANCELACION,
            'motivoCancelacion'        => '',
            'motivoAclaracion'         => '',
            'error'                    => '',
            'errores'                  => [],
        ], $datos));
    }

    /**
     * POST /pedido/{id}/editar
     *
     * Solo se editan productos/cantidades y fecha de entrega (HU-10): cliente
     * y tipo de pedido son fijos y no se leen del POST.
     */
    public function update(int $id): void
    {
        requireAuth();
        requireRole('pedido');

        $pedido = Pedido::buscarPorId($id);

        if ($pedido === null) {
            $this->noEncontrado();
            return;
        }

        $rol      = (string) (usuario_actual()['rol'] ?? '');
        $editable = $this->esEditable((string) $pedido['estado'], $rol);

        // El estado (y el rol, para "En preparación") se revisan siempre acá,
        // sin confiar en que el frontend ya haya deshabilitado el formulario.
        if (!$editable) {
            http_response_code(400);
            $this->showEditForm($id);
            return;
        }

        $campos  = $this->camposDelPostEdicion();
        $errores = array_merge(
            $this->validarFechaEntrega($campos['fechaEntregaRaw'], $campos['fechaEntregaDt']),
            $this->validarProductos($campos['productos'])
        );

        if ($errores !== []) {
            http_response_code(400);
            $this->showEditForm($id, [
                'error'        => self::ERROR_GENERICO,
                'errores'      => $errores,
                'fechaEntrega' => $campos['fechaEntregaRaw'],
                'productos'    => $campos['productos'] !== [] ? $campos['productos'] : [['id_producto' => '', 'cantidad' => '']],
            ]);
            return;
        }

        // Si llegamos hasta acá con el pedido "En preparación", esEditable()
        // ya garantizó que el rol es administrador.
        $enPreparacion = $pedido['estado'] === self::ESTADO_EDITABLE_SOLO_ADMIN;

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            Pedido::actualizar($id, [
                'fecha_entrega'               => $campos['fechaEntregaDt']->format('Y-m-d H:i:s'),
                'usuario_ultima_modificacion' => (int) usuario_actual()['id'],
                'modificado_en_preparacion'   => $enPreparacion,
            ]);

            // Se borra todo el detalle viejo y se reinserta el nuevo: más
            // simple que un diff fila por fila y, dentro de la transacción,
            // igual de seguro (no quedan huérfanos si algo falla a mitad).
            DetallePedido::eliminarPorPedido($id);

            foreach ($campos['productos'] as $item) {
                DetallePedido::crear($id, (int) $item['id_producto'], $item['cantidad']);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        app_log(sprintf(
            'PEDIDO EDICION id=%d en_preparacion=%s email=%s',
            $id,
            $enPreparacion ? '1' : '0',
            $this->emailActual()
        ));

        flash_set(sprintf('Los cambios del pedido #%d se guardaron.', $id), 'ok');
        redirect('pedido');
    }

    /**
     * POST /pedido/{id}/estado
     *
     * Baja lógica (HU-12): pasa el pedido a "Cancelado" con su motivo. Nunca
     * borra el registro, mismo criterio que TipoProducto/Producto/Usuario.
     *
     * Dos rechazos posibles, con códigos distintos a propósito:
     *   - 400: el estado del pedido ya no admite cancelación (regla de
     *     negocio/estado, mismo criterio que update() con "no editable").
     *   - 403: el estado sí admite cancelación, pero este usuario puntual no
     *     tiene permiso sobre ESTE pedido (cajero que no es el dueño, o que
     *     se le venció la ventana de 30 minutos) — mismo código que ya usa
     *     requireRole() para los rechazos de rol.
     */
    public function toggleEstado(int $id): void
    {
        requireAuth();
        requireRole('pedido');

        $pedido = Pedido::buscarPorId($id);

        if ($pedido === null) {
            $this->noEncontrado();
            return;
        }

        if (!$this->esEstadoCancelable((string) $pedido['estado'])) {
            http_response_code(400);
            $this->showEditForm($id, ['error' => self::MOTIVO_NO_CANCELABLE]);
            return;
        }

        $rol       = (string) (usuario_actual()['rol'] ?? '');
        $idUsuario = (int) usuario_actual()['id'];

        if (!$this->puedeCancelar($pedido, $rol, $idUsuario)) {
            http_response_code(403);
            view('errors/403', ['modulo' => 'pedido']);
            return;
        }

        $motivo     = trim((string) ($_POST['motivo'] ?? ''));
        $aclaracion = trim((string) ($_POST['motivo_aclaracion'] ?? ''));
        $error      = $this->validarMotivoCancelacion($motivo, $aclaracion);

        if ($error !== '') {
            http_response_code(400);
            $this->showEditForm($id, [
                'error'              => $error,
                'mostrarCancelacion' => true,
                'motivoCancelacion'  => $motivo,
                'motivoAclaracion'   => $aclaracion,
            ]);
            return;
        }

        $motivoFinal = $motivo === 'Otro' ? ('Otro: ' . $aclaracion) : $motivo;

        Pedido::cancelar($id, $motivoFinal);

        app_log(sprintf(
            'PEDIDO CANCELACION id=%d motivo="%s" email=%s',
            $id,
            $motivoFinal,
            $this->emailActual()
        ));

        flash_set(sprintf('El pedido #%d se canceló.', $id), 'ok');
        redirect('pedido');
    }

    /* -----------------------------------------------------------------------
       Helpers privados
       ----------------------------------------------------------------------- */

    /**
     * Junta y normaliza los campos del POST. fechaEntregaDt es un DateTime ya
     * parseado (o null si el formato vino mal), para no repetir el parseo.
     *
     * @return array<string, mixed>
     */
    private function camposDelPost(): array
    {
        $modoCliente = (string) ($_POST['modo_cliente'] ?? 'existente');

        if (!in_array($modoCliente, ['existente', 'nuevo'], true)) {
            $modoCliente = 'existente';
        }

        $fechaEntregaRaw = trim((string) ($_POST['fecha_entrega'] ?? ''));
        $fechaEntregaDt  = $fechaEntregaRaw !== ''
            ? (\DateTime::createFromFormat('Y-m-d\TH:i', $fechaEntregaRaw) ?: null)
            : null;

        $productos = [];

        foreach ((array) ($_POST['productos'] ?? []) as $fila) {
            $productos[] = [
                'id_producto' => trim((string) ($fila['id_producto'] ?? '')),
                // La coma decimal se normaliza igual que en el precio de Producto.
                'cantidad'    => str_replace(',', '.', trim((string) ($fila['cantidad'] ?? ''))),
            ];
        }

        return [
            'modoCliente'          => $modoCliente,
            'idCliente'            => trim((string) ($_POST['id_cliente'] ?? '')),
            'clienteNuevo'         => [
                'nombre_razon_social' => trim((string) ($_POST['cliente_nombre'] ?? '')),
                'tipo_cliente'        => trim((string) ($_POST['cliente_tipo'] ?? '')),
                'cuit_cuil'           => trim((string) ($_POST['cliente_cuit'] ?? '')),
                'telefono'            => trim((string) ($_POST['cliente_telefono'] ?? '')),
                'email'               => trim((string) ($_POST['cliente_email'] ?? '')),
                'direccion'           => trim((string) ($_POST['cliente_direccion'] ?? '')),
            ],
            'id_tipo_pedido'       => trim((string) ($_POST['id_tipo_pedido'] ?? '')),
            'fechaEntregaRaw'      => $fechaEntregaRaw,
            'fechaEntregaDt'       => $fechaEntregaDt,
            'productos'            => $productos,
            'confirmarFueraPlazo'  => ($_POST['confirmar_fuera_plazo'] ?? '') === '1',
        ];
    }

    /**
     * @param array<string, mixed> $campos
     * @return array<string, string>
     */
    private function validar(array $campos): array
    {
        $errores = [];

        // --- Cliente ---
        if ($campos['modoCliente'] === 'existente') {
            if ($campos['idCliente'] === '') {
                $errores['id_cliente'] = 'Elegí un cliente';
            } elseif (!ctype_digit($campos['idCliente'])) {
                $errores['id_cliente'] = 'Elegí un cliente válido';
            } else {
                $cliente = Cliente::buscarPorId((int) $campos['idCliente']);

                if ($cliente === null || !(bool) $cliente['activo']) {
                    $errores['id_cliente'] = 'El cliente seleccionado no existe';
                }
            }
        } else {
            $nombre = $campos['clienteNuevo']['nombre_razon_social'];
            $tipo   = $campos['clienteNuevo']['tipo_cliente'];

            if ($nombre === '') {
                $errores['cliente_nombre'] = 'Este campo es obligatorio';
            } elseif (mb_strlen($nombre) > 150) {
                $errores['cliente_nombre'] = 'No puede superar los 150 caracteres';
            }

            if (!in_array($tipo, ['Persona', 'Empresa'], true)) {
                $errores['cliente_tipo'] = 'Elegí un tipo de cliente';
            }

            if (mb_strlen($campos['clienteNuevo']['cuit_cuil']) > 20) {
                $errores['cliente_cuit'] = 'No puede superar los 20 caracteres';
            }

            if (mb_strlen($campos['clienteNuevo']['telefono']) > 30) {
                $errores['cliente_telefono'] = 'No puede superar los 30 caracteres';
            }

            $email = $campos['clienteNuevo']['email'];

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores['cliente_email'] = 'Ingresá un correo electrónico válido';
            } elseif (mb_strlen($email) > 150) {
                $errores['cliente_email'] = 'No puede superar los 150 caracteres';
            }

            if (mb_strlen($campos['clienteNuevo']['direccion']) > 255) {
                $errores['cliente_direccion'] = 'No puede superar los 255 caracteres';
            }
        }

        // --- Tipo de pedido ---
        if ($campos['id_tipo_pedido'] === '') {
            $errores['id_tipo_pedido'] = 'Elegí un tipo de pedido';
        } elseif (!ctype_digit($campos['id_tipo_pedido']) || TipoPedido::buscarPorId((int) $campos['id_tipo_pedido']) === null) {
            $errores['id_tipo_pedido'] = 'El tipo de pedido seleccionado no existe';
        }

        // --- Fecha de entrega ---
        // La anticipación mínima (48hs para "Evento", etc.) se revisa en
        // store(), después de validar() acá: necesita el tipo de pedido ya
        // confirmado como válido y puede terminar en un aviso, no en un error.
        $errores = array_merge($errores, $this->validarFechaEntrega($campos['fechaEntregaRaw'], $campos['fechaEntregaDt']));

        // --- Productos ---
        $errores = array_merge($errores, $this->validarProductos($campos['productos']));

        return $errores;
    }

    /**
     * Valida solo la fecha de entrega. Se usa tanto en el alta como en la
     * edición (HU-10): la modificación no vuelve a chequear la anticipación
     * mínima de "Evento", solo que la fecha tenga formato válido.
     *
     * @return array<string, string>
     */
    private function validarFechaEntrega(string $fechaEntregaRaw, ?\DateTime $fechaEntregaDt): array
    {
        if ($fechaEntregaRaw === '') {
            return ['fecha_entrega' => 'Este campo es obligatorio'];
        }

        if ($fechaEntregaDt === null) {
            return ['fecha_entrega' => 'Ingresá una fecha y hora válidas'];
        }

        return [];
    }

    /**
     * Valida la lista de productos del pedido. Se usa tanto en el alta como
     * en la edición (HU-10).
     *
     * @param array<int, array{id_producto: string, cantidad: string}> $productos
     * @return array<string, string>
     */
    private function validarProductos(array $productos): array
    {
        if ($productos === [] || $this->todasLasFilasVacias($productos)) {
            return ['productos' => 'Agregá al menos un producto'];
        }

        $vistos = [];

        foreach ($productos as $fila) {
            if ($fila['id_producto'] === '' && $fila['cantidad'] === '') {
                continue; // fila vacía sin usar (quedó del template), se ignora
            }

            if ($fila['id_producto'] === '' || !ctype_digit($fila['id_producto'])) {
                return ['productos' => 'Elegí un producto válido en cada fila'];
            }

            $producto = Producto::buscarPorId((int) $fila['id_producto']);

            if ($producto === null || !(bool) $producto['activo']) {
                return ['productos' => 'Uno de los productos elegidos no existe o está inactivo'];
            }

            if (isset($vistos[$fila['id_producto']])) {
                return ['productos' => 'No repitas el mismo producto en dos filas: sumá la cantidad en una sola'];
            }

            $vistos[$fila['id_producto']] = true;

            if ($fila['cantidad'] === '' || !is_numeric($fila['cantidad']) || (float) $fila['cantidad'] <= 0) {
                return ['productos' => 'La cantidad de cada producto debe ser un número mayor a cero'];
            }
        }

        return [];
    }

    /**
     * @param array<int, array{id_producto: string, cantidad: string}> $productos
     */
    private function todasLasFilasVacias(array $productos): bool
    {
        foreach ($productos as $fila) {
            if ($fila['id_producto'] !== '' || $fila['cantidad'] !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Compara la fecha de entrega contra la anticipación mínima del tipo de
     * pedido elegido.
     *
     * @param array<string, mixed>|null $tipoPedido
     * @return 'ok'|'vencida'|'insuficiente'
     */
    private function revisarAnticipacion(?array $tipoPedido, ?\DateTime $fechaEntrega): string
    {
        if ($tipoPedido === null || $fechaEntrega === null) {
            return 'ok'; // ya se rechazó antes por otro motivo; no hay nada más que revisar acá
        }

        $ahora = new \DateTime();

        if ($fechaEntrega < $ahora) {
            return 'vencida';
        }

        $horas = (int) $tipoPedido['anticipacion_minima_horas'];

        if ($horas <= 0) {
            return 'ok';
        }

        $minimo = (clone $ahora)->modify(sprintf('+%d hours', $horas));

        return $fechaEntrega < $minimo ? 'insuficiente' : 'ok';
    }

    /**
     * Arma el array de datos para volver a mostrar el formulario con lo que
     * la persona ya había cargado, tras un error o un aviso de anticipación.
     *
     * @param array<string, mixed> $campos
     * @return array<string, mixed>
     */
    private function datosParaRedisplay(array $campos): array
    {
        $productos = $campos['productos'] !== [] ? $campos['productos'] : [['id_producto' => '', 'cantidad' => '']];

        return [
            'tiposPedido'          => TipoPedido::listar(),
            'clientes'             => Cliente::listar(),
            'productosDisponibles' => Producto::listar(null, 'activo'),
            'modoCliente'          => $campos['modoCliente'],
            'idCliente'            => $campos['idCliente'],
            'clienteNuevo'         => $campos['clienteNuevo'],
            'idTipoPedido'         => $campos['id_tipo_pedido'],
            'fechaEntrega'         => $campos['fechaEntregaRaw'],
            'productos'            => $productos,
            'confirmarFueraPlazo'  => $campos['confirmarFueraPlazo'],
        ];
    }

    /**
     * Junta y normaliza los campos del POST de edición (HU-10): solo fecha de
     * entrega y productos. Cliente y tipo de pedido son fijos, no se leen acá.
     *
     * @return array<string, mixed>
     */
    private function camposDelPostEdicion(): array
    {
        $fechaEntregaRaw = trim((string) ($_POST['fecha_entrega'] ?? ''));
        $fechaEntregaDt  = $fechaEntregaRaw !== ''
            ? (\DateTime::createFromFormat('Y-m-d\TH:i', $fechaEntregaRaw) ?: null)
            : null;

        $productos = [];

        foreach ((array) ($_POST['productos'] ?? []) as $fila) {
            $productos[] = [
                'id_producto' => trim((string) ($fila['id_producto'] ?? '')),
                'cantidad'    => str_replace(',', '.', trim((string) ($fila['cantidad'] ?? ''))),
            ];
        }

        return [
            'fechaEntregaRaw' => $fechaEntregaRaw,
            'fechaEntregaDt'  => $fechaEntregaDt,
            'productos'       => $productos,
        ];
    }

    /**
     * ¿Se puede modificar un pedido en este estado, con este rol? (HU-10)
     *
     * showEditForm() usa requireRole('pedido-consulta') (los cuatro roles
     * pueden abrir el detalle, HU-10/HU-11), así que acá ya no vale asumir
     * que quien llega tiene permiso de módulo: se chequea primero. Solo
     * administrador y cajero llegan a mirar el estado; maestro_panadero y
     * repartidor ven cualquier pedido en solo lectura, sea cual sea su estado.
     *
     * Con el rol filtrado: Nuevo/Confirmado, cualquiera de los dos. En
     * preparación, solo administrador. Cualquier otro estado
     * (Listo/Entregado/Cancelado), nadie.
     */
    private function esEditable(string $estado, string $rol): bool
    {
        if (!in_array($rol, ['administrador', 'cajero'], true)) {
            return false;
        }

        if (in_array($estado, self::ESTADOS_EDITABLES, true)) {
            return true;
        }

        if ($estado === self::ESTADO_EDITABLE_SOLO_ADMIN) {
            return $rol === 'administrador';
        }

        return false;
    }

    /**
     * ¿El estado del pedido todavía admite una cancelación? No mira quién la
     * pide: eso es puedeCancelar(). Separadas a propósito para poder devolver
     * 400 (estado) y 403 (permiso) por separado en toggleEstado().
     */
    private function esEstadoCancelable(string $estado): bool
    {
        return in_array($estado, self::ESTADOS_CANCELABLES, true);
    }

    /**
     * ¿Este usuario puntual puede cancelar este pedido puntual? Asume que el
     * estado ya se validó aparte (esEstadoCancelable()).
     *
     * Administrador: siempre. Cajero: solo si el pedido lo creó él mismo y
     * todavía no pasaron 30 minutos desde fecha_creacion. Cualquier otro rol
     * no debería llegar acá (requireRole('pedido') ya los filtró antes).
     */
    private function puedeCancelar(array $pedido, string $rol, int $idUsuarioActual): bool
    {
        if ($rol === 'administrador') {
            return true;
        }

        if ($rol !== 'cajero') {
            return false;
        }

        if ((int) $pedido['usuario_registro'] !== $idUsuarioActual) {
            return false;
        }

        $creado = new \DateTime((string) $pedido['fecha_creacion']);
        $limite = (clone $creado)->modify(sprintf('+%d minutes', self::VENTANA_CANCELACION_CAJERO_MINUTOS));

        return new \DateTime() <= $limite;
    }

    /**
     * Combina estado + permiso: la usa showEditForm() para decidir si
     * muestra el botón "Cancelar pedido". toggleEstado() chequea las dos
     * reglas por separado (esEstadoCancelable() y puedeCancelar()) para
     * poder distinguir 400 de 403 en el rechazo.
     */
    private function esCancelable(array $pedido, string $rol, int $idUsuarioActual): bool
    {
        return $this->esEstadoCancelable((string) $pedido['estado'])
            && $this->puedeCancelar($pedido, $rol, $idUsuarioActual);
    }

    /**
     * Motivo obligatorio de una lista predefinida; si es "Otro", la
     * aclaración también es obligatoria. Devuelve el mensaje de error, o ''
     * si está todo bien.
     */
    private function validarMotivoCancelacion(string $motivo, string $aclaracion): string
    {
        if ($motivo === '' || !in_array($motivo, self::MOTIVOS_CANCELACION, true)) {
            return 'Elegí un motivo de cancelación.';
        }

        if ($motivo === 'Otro' && $aclaracion === '') {
            return 'Contá brevemente el motivo en el campo de aclaración.';
        }

        return '';
    }

    /**
     * Detalle de un pedido en la forma que espera pedido/form.php. Si por
     * algún motivo no tuviera productos cargados (no debería pasar en la
     * práctica), devuelve una fila vacía para que el formulario tenga algo
     * que mostrar.
     *
     * @return array<int, array{id_producto: string, cantidad: string}>
     */
    private function detalleParaFormulario(int $idPedido): array
    {
        $detalle = DetallePedido::listarPorPedido($idPedido);

        if ($detalle === []) {
            return [['id_producto' => '', 'cantidad' => '']];
        }

        return array_map(static fn (array $d): array => [
            'id_producto' => (string) $d['id_producto'],
            'cantidad'    => (string) $d['cantidad'],
        ], $detalle);
    }

    /**
     * "2026-09-20 10:00:00" (MySQL) -> "2026-09-20T10:00" (input datetime-local).
     */
    private function formatearFechaLocal(string $fechaMysql): string
    {
        return (new \DateTime($fechaMysql))->format('Y-m-d\TH:i');
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

    /**
     * Lee y valida los filtros de la query string de index() (HU-11).
     * Cualquier valor raro (id no numérico, estado que no existe, fecha con
     * formato inválido) se descarta en vez de pasarlo a la consulta SQL, mismo
     * criterio que ya usa TipoProductoController::index() con el filtro de
     * estado.
     *
     * @return array<string, int|string>
     */
    private function filtrosDesdeQuery(): array
    {
        $filtros = [];

        $cliente = trim((string) ($_GET['cliente'] ?? ''));

        if ($cliente !== '') {
            $filtros['cliente_nombre'] = $cliente;
        }

        $tipo = (string) ($_GET['tipo'] ?? '');

        if ($tipo !== '' && ctype_digit($tipo)) {
            $filtros['id_tipo_pedido'] = (int) $tipo;
        }

        $estado = (string) ($_GET['estado'] ?? '');

        if (in_array($estado, self::ESTADOS, true)) {
            $filtros['estado'] = $estado;
        }

        $desde = (string) ($_GET['fecha_desde'] ?? '');

        if ($this->esFechaValida($desde)) {
            $filtros['fecha_desde'] = $desde;
        }

        $hasta = (string) ($_GET['fecha_hasta'] ?? '');

        if ($this->esFechaValida($hasta)) {
            $filtros['fecha_hasta'] = $hasta;
        }

        return $filtros;
    }

    /**
     * "2026-09-20", con el formato exacto (evita que "2026-9-2" o basura
     * parecida a una fecha se cuele en la consulta SQL).
     */
    private function esFechaValida(string $fecha): bool
    {
        if ($fecha === '') {
            return false;
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $dt !== false && $dt->format('Y-m-d') === $fecha;
    }

    /**
     * ¿La solicitud vino del fetch de filtros de pedido/index.php (HU-11)?
     * Si es así, index() devuelve solo el fragmento de la tabla, sin layout.
     */
    private function esSolicitudFetch(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
