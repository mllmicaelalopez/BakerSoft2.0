<?php

declare(strict_types=1);

/**
 * BakerSoft - Confirmación de baja de categoría con productos activos (HT-03).
 *
 * No hay cascada: al confirmar, solo se desactiva la categoría. Los productos
 * asociados siguen exactamente como están (ver TipoProductoController::toggleEstado).
 *
 * @var array<string, mixed> $tipo
 * @var int $cantidadActivos
 */

?>

<div class="abm-form-caja">

    <p class="abm-alerta abm-alerta-error" role="alert">
        "<?= e($tipo['nombre']) ?>" tiene
        <?= $cantidadActivos === 1 ? '1 producto activo' : $cantidadActivos . ' productos activos' ?>.
        Al desactivarla, <?= $cantidadActivos === 1 ? 'ese producto seguirá activo' : 'esos productos seguirán activos' ?>
        y visible<?= $cantidadActivos === 1 ? '' : 's' ?> con su categoría desactivada.
        ¿Confirmás la baja de todas formas?
    </p>

    <form method="post" action="<?= e(base_url('tipo-producto/' . $tipo['id_tipo_producto'] . '/estado')) ?>">
        <input type="hidden" name="confirmar" value="1">

        <div class="abm-form-pie">
            <a class="abm-btn-secundario" href="<?= e(base_url('tipo-producto')) ?>">Cancelar</a>
            <button class="abm-btn" type="submit">Confirmar baja</button>
        </div>
    </form>

</div>
