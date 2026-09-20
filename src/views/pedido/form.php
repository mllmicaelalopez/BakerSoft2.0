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
 * @var array<int, array{id_producto: string, cantidad: string}> $productos
 * @var bool $anticipacionInsuficiente  Solo aplica en modo "crear".
 * @var bool $confirmarFueraPlazo       Solo aplica en modo "crear".
 * @var bool $soloLectura               Solo aplica en modo "editar".
 * @var string $motivoSoloLectura
 * @var bool $advertenciaEnPreparacion  Solo aplica en modo "editar".
 * @var bool $puedeCancelar             Solo aplica en modo "editar".
 * @var bool $mostrarCancelacion        Arranca abierto el bloque de motivo (redisplay tras error).
 * @var array<int, string> $motivosCancelacion
 * @var string $motivoCancelacion
 * @var string $motivoAclaracion
 * @var string $error
 * @var array<string, string> $errores
 */

$esEdicion    = $modo === 'editar';
$deshabilitar = $esEdicion && $soloLectura;

$accion = $esEdicion
    ? base_url('pedido/' . $pedido['id_pedido'] . '/editar')
    : base_url('pedido/nuevo');

// Arma las <option> del select de producto; se usa para cada fila ya cargada
// y también adentro del <template> que la fila nueva clona por JS.
$opcionesProducto = static function (string $seleccionado) use ($productosDisponibles): string {
    $html = '<option value="">Elegí un producto</option>';

    foreach ($productosDisponibles as $prod) {
        $valor = (string) $prod['id_producto'];
        $html .= sprintf(
            '<option value="%s"%s>%s</option>',
            e($valor),
            $seleccionado === $valor ? ' selected' : '',
            e($prod['nombre'])
        );
    }

    return $html;
};

?>

<div class="abm-form-caja abm-form-ancho">

    <?php if ($esEdicion && $soloLectura): ?>
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
                   value="<?= e($fechaEntrega) ?>" <?= $deshabilitar ? 'disabled' : '' ?>>
            <?php if (isset($errores['fecha_entrega'])): ?>
                <span class="abm-ayuda abm-ayuda-error"><?= e($errores['fecha_entrega']) ?></span>
            <?php endif; ?>

            <?php if (!$esEdicion): ?>
                <!--
                    Aviso de anticipación mínima: solo aplica al alta (HU-09).
                    La modificación (HU-10) no vuelve a chequear las 48hs de
                    "Evento" acá a propósito.
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

        <div class="abm-campo">
            <span class="abm-label">Productos<span class="abm-requerido">*</span></span>

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
