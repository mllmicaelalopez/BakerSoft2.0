<?php

declare(strict_types=1);

/**
 * BakerSoft - Alta, modificación y cancelación de pedido (HU-09, HU-10 y
 * HU-12, Sprint 4). La misma vista sirve para los tres, mismo criterio que
 * tipo-producto/form.php: $modo ("crear"/"editar") y los campos que no se
 * pueden tocar en edición (acá: cliente y tipo de pedido) van deshabilitados.
 *
 * En edición, además, un pedido puede no ser modificable según su estado (o
 * según el rol si está "En preparación"): $soloLectura deshabilita todo el
 * formulario y no se manda ningún submit.
 *
 * La cancelación (HU-12) es una capacidad aparte de la edición, con su propia
 * regla de habilitación ($puedeCancelar): un pedido puede estar soloLectura
 * para editar productos/fecha y seguir siendo cancelable (o al revés), así
 * que el botón "Cancelar pedido" no depende de $soloLectura. Va en un <form>
 * propio (action distinto: .../estado) con el mismo criterio de bloque
 * inline oculto por defecto que ya usa #aviso-anticipacion (HU-09).
 *
 * La misma vista sirve también para "Ver detalle" (PedidoController::show(),
 * pedido/{id}/ver): $soloLectura en true con $motivoSoloLectura vacío es la
 * vista de consulta, sin advertencia, sin "Guardar" y sin "Cancelar pedido".
 *
 * El avance de estado (Figura 1 del Sprint 4: Nuevo -> Confirmado -> En
 * preparación -> Listo -> Entregado) es otra capacidad independiente de la
 * edición, con su propio <form> (action .../avanzar; un <form> no puede ir
 * adentro de otro) y su bloque de confirmación inline, igual que la
 * cancelación. Se muestra, también en el detalle de solo lectura, solo si
 * $transicion no es null (el rol tiene el permiso de ese paso). Sin JS, el
 * botón principal envía directo (mejora progresiva).
 *
 * @var string $modo                    "crear" o "editar"
 * @var array<string, mixed>|null $pedido  Solo en modo "editar".
 * @var array<int, array<string, mixed>> $tiposPedido
 * @var array<int, array<string, mixed>> $clientes
 * @var array<int, array<string, mixed>> $productosDisponibles
 * @var string $modoCliente         "existente" o "nuevo" (solo aplica en modo "crear")
 * @var string $idCliente
 * @var array<string, string> $clienteNuevo
 * @var string $idTipoPedido
 * @var string $fechaEntrega
 * @var string $fechaEntregaOriginal     Solo en modo "editar": la fecha guardada (Y-m-d\TH:i).
 * @var int $anticipacionMinimaHoras     Solo en modo "editar": horas de anticipación del tipo de pedido.
 * @var array<int, array{id_producto: string, cantidad: string}> $productos
 * @var bool $anticipacionInsuficiente  Aplica al alta y a la edición editable (no al solo lectura).
 * @var bool $confirmarFueraPlazo       Aplica al alta y a la edición editable (no al solo lectura).
 * @var bool $soloLectura               Solo aplica en modo "editar".
 * @var string $motivoSoloLectura
 * @var bool $advertenciaEnPreparacion  Solo aplica en modo "editar".
 * @var bool $puedeCancelar             Solo aplica en modo "editar".
 * @var array{siguiente: string, accion: string, capacidad: string}|null $transicion
 *                                      Solo en modo "editar": el paso de estado que este rol puede
 *                                      hacer sobre este pedido, o null si no hay botón.
 * @var bool $mostrarCancelacion       Arranca abierto el bloque de motivo (redisplay tras error).
 * @var array<int, string> $motivosCancelacion
 * @var string $motivoCancelacion
 * @var string $motivoAclaracion
 * @var string $error
 * @var array<string, string> $errores
 */

$esEdicion    = $modo === 'editar';
$deshabilitar = $esEdicion && $soloLectura;

// Mensaje de un POST exitoso que redirige acá (por ejemplo, el avance de
// estado vuelve al detalle del pedido).
$flash = $esEdicion ? flash_get() : null;

$accion = $esEdicion
    ? base_url('pedido/' . $pedido['id_pedido'] . '/editar')
    : base_url('pedido/nuevo');

// Arma las <option> del select de producto; se usa para cada fila ya cargada
// y también adentro del <template> que la fila nueva clona por JS.
$opcionesProducto = static function (string $seleccionado) use ($productosDisponibles): string {
    $html = '<option value="">Elegí un producto</option>';

    foreach ($productosDisponibles as $prod) {
        $valor = (string) $prod['id_producto'];
        // data-unidad y data-nombre los usa assets/js/app.js para validar la
        // cantidad (entera si es por unidad) con el mismo texto que el servidor.
        $html .= sprintf(
            '<option value="%s" data-unidad="%s" data-nombre="%s"%s>%s</option>',
            e($valor),
            $prod['unidad_medida'] === 'kg' ? 'kg' : 'unidad',
            e($prod['nombre']),
            $seleccionado === $valor ? ' selected' : '',
            e($prod['nombre'] . ' (' . ($prod['unidad_medida'] === 'kg' ? 'kg' : 'unidad') . ')')
        );
    }

    return $html;
};

?>

<div class="abm-form-caja abm-form-ancho">

    <?php if ($flash !== null): ?>
        <p class="abm-alerta abm-alerta-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensaje']) ?></p>
    <?php endif; ?>

    <?php if ($esEdicion): ?>
        <p class="abm-estado-linea">
            Estado:
            <span class="abm-badge <?= e(clase_badge_estado((string) $pedido['estado'])) ?>"><?= e($pedido['estado']) ?></span>
        </p>
    <?php endif; ?>

    <?php if ($esEdicion && $soloLectura && $motivoSoloLectura !== ''): ?>
        <p class="abm-alerta abm-alerta-error" role="alert"><?= e($motivoSoloLectura) ?></p>
    <?php endif; ?>

    <?php if ($esEdicion && !$soloLectura && $advertenciaEnPreparacion): ?>
        <p class="abm-alerta abm-alerta-error" role="alert">
            Este pedido ya está en preparación. La modificación va a quedar registrada.
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="abm-alerta abm-alerta-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e($accion) ?>" id="form-pedido" novalidate>

        <div class="abm-campo">
            <span class="abm-label">Cliente</span>
            <?php if ($esEdicion): ?>
                <input class="abm-input" type="text" value="<?= e($pedido['cliente']) ?>" disabled>
            <?php else: ?>
                <div class="abm-campo-radios">
                    <label>
                        <input type="radio" name="modo_cliente" value="existente" id="modo-cliente-existente"
                            <?= $modoCliente === 'existente' ? 'checked' : '' ?>>
                        Cliente existente
                    </label>
                    <label>
                        <input type="radio" name="modo_cliente" value="nuevo" id="modo-cliente-nuevo"
                            <?= $modoCliente === 'nuevo' ? 'checked' : '' ?>>
                        Cliente nuevo
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$esEdicion): ?>

            <div id="bloque-cliente-existente" <?= $modoCliente !== 'existente' ? 'hidden' : '' ?>>
                <div class="abm-campo">
                    <label class="abm-label" for="id_cliente">Seleccioná un cliente<span class="abm-requerido">*</span></label>
                    <select class="abm-input<?= isset($errores['id_cliente']) ? ' abm-input-error' : '' ?>"
                            id="id_cliente" name="id_cliente">
                        <option value="">Elegí un cliente</option>
                        <?php foreach ($clientes as $cli): ?>
                            <option value="<?= e((string) $cli['id_cliente']) ?>"
                                <?= $idCliente === (string) $cli['id_cliente'] ? 'selected' : '' ?>>
                                <?= e($cli['nombre_razon_social']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['id_cliente'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['id_cliente']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div id="bloque-cliente-nuevo" <?= $modoCliente !== 'nuevo' ? 'hidden' : '' ?>>

                <div class="abm-campo">
                    <label class="abm-label" for="cliente_nombre">Nombre / razón social<span class="abm-requerido">*</span></label>
                    <input class="abm-input<?= isset($errores['cliente_nombre']) ? ' abm-input-error' : '' ?>"
                           type="text" id="cliente_nombre" name="cliente_nombre"
                           value="<?= e($clienteNuevo['nombre_razon_social']) ?>" maxlength="150">
                    <?php if (isset($errores['cliente_nombre'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['cliente_nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="cliente_tipo">Tipo de cliente<span class="abm-requerido">*</span></label>
                    <select class="abm-input<?= isset($errores['cliente_tipo']) ? ' abm-input-error' : '' ?>"
                            id="cliente_tipo" name="cliente_tipo">
                        <option value="">Elegí un tipo</option>
                        <option value="Persona" <?= $clienteNuevo['tipo_cliente'] === 'Persona' ? 'selected' : '' ?>>Persona</option>
                        <option value="Empresa" <?= $clienteNuevo['tipo_cliente'] === 'Empresa' ? 'selected' : '' ?>>Empresa</option>
                    </select>
                    <?php if (isset($errores['cliente_tipo'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['cliente_tipo']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="cliente_cuit">CUIT / CUIL</label>
                    <input class="abm-input<?= isset($errores['cliente_cuit']) ? ' abm-input-error' : '' ?>"
                           type="text" id="cliente_cuit" name="cliente_cuit"
                           value="<?= e($clienteNuevo['cuit_cuil']) ?>" maxlength="20" placeholder="Opcional">
                    <?php if (isset($errores['cliente_cuit'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['cliente_cuit']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="cliente_telefono">Teléfono</label>
                    <input class="abm-input<?= isset($errores['cliente_telefono']) ? ' abm-input-error' : '' ?>"
                           type="text" id="cliente_telefono" name="cliente_telefono"
                           value="<?= e($clienteNuevo['telefono']) ?>" maxlength="30" placeholder="Opcional">
                    <?php if (isset($errores['cliente_telefono'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['cliente_telefono']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="cliente_email">Email</label>
                    <input class="abm-input<?= isset($errores['cliente_email']) ? ' abm-input-error' : '' ?>"
                           type="email" id="cliente_email" name="cliente_email"
                           value="<?= e($clienteNuevo['email']) ?>" maxlength="150" placeholder="Opcional">
                    <?php if (isset($errores['cliente_email'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['cliente_email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="abm-campo">
                    <label class="abm-label" for="cliente_direccion">Dirección</label>
                    <input class="abm-input<?= isset($errores['cliente_direccion']) ? ' abm-input-error' : '' ?>"
                           type="text" id="cliente_direccion" name="cliente_direccion"
                           value="<?= e($clienteNuevo['direccion']) ?>" maxlength="255" placeholder="Opcional">
                    <?php if (isset($errores['cliente_direccion'])): ?>
                        <span class="abm-ayuda abm-ayuda-error"><?= e($errores['cliente_direccion']) ?></span>
                    <?php endif; ?>
                </div>

            </div>

        <?php endif; ?>

        <div class="abm-campo">
            <label class="abm-label" for="id_tipo_pedido">Tipo de pedido<span class="abm-requerido">*</span></label>
            <?php if ($esEdicion): ?>
                <input class="abm-input" type="text" value="<?= e($pedido['tipo_pedido']) ?>" disabled>
            <?php else: ?>
                <select class="abm-input<?= isset($errores['id_tipo_pedido']) ? ' abm-input-error' : '' ?>"
                        id="id_tipo_pedido" name="id_tipo_pedido">
                    <option value="">Elegí un tipo de pedido</option>
                    <?php foreach ($tiposPedido as $tp): ?>
                        <option value="<?= e((string) $tp['id_tipo_pedido']) ?>"
                            data-anticipacion="<?= e((string) $tp['anticipacion_minima_horas']) ?>"
                            <?= $idTipoPedido === (string) $tp['id_tipo_pedido'] ? 'selected' : '' ?>>
                            <?= e($tp['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['id_tipo_pedido'])): ?>
                    <span class="abm-ayuda abm-ayuda-error"><?= e($errores['id_tipo_pedido']) ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="abm-campo">
            <label class="abm-label" for="fecha_entrega">Fecha y hora de entrega<span class="abm-requerido">*</span></label>
            <input class="abm-input<?= isset($errores['fecha_entrega']) ? ' abm-input-error' : '' ?>"
                   type="datetime-local" id="fecha_entrega" name="fecha_entrega"
                   <?php if ($esEdicion): ?>
                   data-anticipacion="<?= e((string) $anticipacionMinimaHoras) ?>"
                   data-fecha-original="<?= e($fechaEntregaOriginal) ?>"
                   <?php endif; ?>
                   value="<?= e($fechaEntrega) ?>" <?= $deshabilitar ? 'disabled' : '' ?>>
            <?php if (isset($errores['fecha_entrega'])): ?>
                <span class="abm-ayuda abm-ayuda-error"><?= e($errores['fecha_entrega']) ?></span>
            <?php endif; ?>

            <?php if (!$deshabilitar): ?>
                <!--
                    Aviso de anticipación mínima: aplica al alta (HU-09) y a la
                    edición cuando la fecha de entrega cambia (HU-10). En solo
                    lectura y en "Ver detalle" no se renderiza.
                -->
                <div id="aviso-anticipacion" class="abm-alerta abm-alerta-error" role="alert"
                     <?= !$anticipacionInsuficiente ? 'hidden' : '' ?>>
                    Este tipo de pedido pide una anticipación mínima que la fecha elegida no cumple.
                    ¿Modificás la fecha o confirmás igual?
                    <div class="abm-form-pie">
                        <button type="button" id="btn-modificar-fecha" class="abm-btn-secundario">Modificar fecha</button>
                        <button type="button" id="btn-confirmar-fuera-plazo" class="abm-btn">Confirmar igual</button>
                    </div>
                </div>
                <input type="hidden" id="confirmar_fuera_plazo" name="confirmar_fuera_plazo"
                       value="<?= $confirmarFueraPlazo ? '1' : '' ?>">
            <?php endif; ?>
        </div>

        <?php if ($esEdicion && $pedido['estado'] === 'Cancelado'): ?>
            <div class="abm-campo">
                <label class="abm-label" for="motivo_cancelacion_registrado">Motivo de la cancelación</label>
                <textarea class="abm-input abm-textarea" id="motivo_cancelacion_registrado" rows="2" disabled><?= e(trim((string) ($pedido['motivo_cancelacion'] ?? '')) !== '' ? (string) $pedido['motivo_cancelacion'] : 'Sin motivo registrado') ?></textarea>
            </div>
        <?php endif; ?>

        <div class="abm-campo">
            <span class="abm-label">Productos<span class="abm-requerido">*</span></span>
            <span class="abm-ayuda">La cantidad se carga en kg para productos por peso, o en unidades enteras para el resto (lo indica cada producto en la lista).</span>

            <div id="productos-filas">
                <?php foreach ($productos as $i => $fila): ?>
                    <div class="abm-fila-producto">
                        <select class="abm-input" name="productos[<?= $i ?>][id_producto]" <?= $deshabilitar ? 'disabled' : '' ?>>
                            <?= $opcionesProducto($fila['id_producto']) ?>
                        </select>
                        <input class="abm-input" type="text" inputmode="decimal"
                               name="productos[<?= $i ?>][cantidad]"
                               value="<?= e($fila['cantidad']) ?>" placeholder="Cantidad" <?= $deshabilitar ? 'disabled' : '' ?>>
                        <?php if (!$deshabilitar): ?>
                            <button type="button" class="abm-btn-secundario abm-btn-quitar-producto">Quitar</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$deshabilitar): ?>
                <button type="button" id="btn-agregar-producto" class="abm-btn-secundario"
                        data-siguiente-indice="<?= count($productos) ?>">
                    Agregar producto
                </button>
            <?php endif; ?>

            <?php if (isset($errores['productos'])): ?>
                <span class="abm-ayuda abm-ayuda-error"><?= e($errores['productos']) ?></span>
            <?php endif; ?>
        </div>

        <div class="abm-form-pie">
            <a class="abm-btn-secundario" href="<?= e(base_url('pedido')) ?>">Volver</a>
            <?php if (!$deshabilitar): ?>
                <button class="abm-btn" type="submit">Guardar</button>
            <?php endif; ?>
        </div>

    </form>

    <?php if ($esEdicion && $transicion !== null): ?>
        <!--
            Form aparte (action .../avanzar): un <form> no puede ir adentro de
            otro. Sin JS el botón principal es un submit y avanza directo; con
            JS (assets/js/app.js) pide confirmación antes. estado_actual viaja
            oculto: el servidor lo compara con la base para no avanzar dos
            pasos si la pantalla quedó vieja.
        -->
        <form method="post" action="<?= e(base_url('pedido/' . $pedido['id_pedido'] . '/avanzar')) ?>"
              id="form-avanzar-estado" class="abm-avance" novalidate>

            <input type="hidden" name="estado_actual" value="<?= e((string) $pedido['estado']) ?>">

            <button type="submit" id="btn-avanzar-estado" class="abm-btn">
                <?= e($transicion['accion']) ?>
            </button>

            <div id="confirmar-avance" class="abm-alerta abm-alerta-info" role="alert" hidden>
                <p>
                    ¿Confirmás pasar el pedido #<?= e((string) $pedido['id_pedido']) ?>
                    de "<?= e((string) $pedido['estado']) ?>" a "<?= e($transicion['siguiente']) ?>"?
                    Este cambio no se puede deshacer.
                </p>
                <div class="abm-form-pie">
                    <button type="button" id="btn-volver-avance" class="abm-btn-secundario">Volver</button>
                    <button class="abm-btn" type="submit">Confirmar</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($esEdicion && $puedeCancelar): ?>
        <!--
            Form aparte: la acción es distinta (.../estado) de la del form de
            arriba (.../editar), y un <form> no puede ir adentro de otro. No
            depende de $deshabilitar: la cancelación es una capacidad
            independiente de poder editar productos/fecha (ver doc arriba).
        -->
        <form method="post" action="<?= e(base_url('pedido/' . $pedido['id_pedido'] . '/estado')) ?>"
              id="form-cancelar-pedido" novalidate>

            <button type="button" id="btn-cancelar-pedido" class="abm-btn-secundario"
                    <?= $mostrarCancelacion ? 'hidden' : '' ?>>
                Cancelar pedido
            </button>

            <div id="confirmar-cancelacion" class="abm-alerta abm-alerta-error" role="alert"
                 <?= !$mostrarCancelacion ? 'hidden' : '' ?>>

                <div class="abm-campo">
                    <label class="abm-label" for="motivo_cancelacion">Motivo de la cancelación<span class="abm-requerido">*</span></label>
                    <select class="abm-input" id="motivo_cancelacion" name="motivo">
                        <option value="">Elegí un motivo</option>
                        <?php foreach ($motivosCancelacion as $m): ?>
                            <option value="<?= e($m) ?>" <?= $motivoCancelacion === $m ? 'selected' : '' ?>>
                                <?= e($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="abm-campo" id="bloque-motivo-aclaracion" <?= $motivoCancelacion !== 'Otro' ? 'hidden' : '' ?>>
                    <label class="abm-label" for="motivo_aclaracion">Aclaración<span class="abm-requerido">*</span></label>
                    <input class="abm-input" type="text" id="motivo_aclaracion" name="motivo_aclaracion"
                           value="<?= e($motivoAclaracion) ?>" maxlength="255">
                </div>

                <div class="abm-form-pie">
                    <button type="button" id="btn-volver-cancelacion" class="abm-btn-secundario">Volver</button>
                    <button class="abm-btn" type="submit">Confirmar cancelación</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if (!$deshabilitar): ?>
        <!-- Plantilla que btn-agregar-producto clona por JS (assets/js/app.js). -->
        <template id="template-fila-producto">
            <div class="abm-fila-producto">
                <select class="abm-input" name="">
                    <?= $opcionesProducto('') ?>
                </select>
                <input class="abm-input" type="text" inputmode="decimal" name="" value="" placeholder="Cantidad">
                <button type="button" class="abm-btn-secundario abm-btn-quitar-producto">Quitar</button>
            </div>
        </template>
    <?php endif; ?>

</div>
