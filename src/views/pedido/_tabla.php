<?php

declare(strict_types=1);

/**
 * BakerSoft - Fragmento de la tabla de pedidos (HU-11: Consulta).
 *
 * Es el mismo bloque que antes vivía directo en pedido/index.php, separado
 * acá para poder reusarlo tal cual en dos casos:
 *   - la carga completa de pedido/index.php (con el layout alrededor), y
 *   - la respuesta a los fetch de filtros de PedidoController::index()
 *     (sin layout: solo este fragmento, que assets/js/app.js inserta en el
 *     DOM reemplazando la tabla actual).
 *
 * Administrador y cajero ven el ícono de editar. maestro_panadero y
 * repartidor ven en su lugar el ícono "Ver detalle": abre la misma pantalla
 * (pedido/{id}/editar), que para esos roles se muestra siempre en solo
 * lectura y sin el botón de cancelar (HU-10, HU-11).
 *
 * @var array<int, array<string, mixed>> $pedidos
 */

$rolActual  = usuario_actual()['rol'] ?? '';
$puedeEditar = in_array($rolActual, ['administrador', 'cajero'], true);

?>

<div class="abm-tabla-caja">
    <?php if ($pedidos === []): ?>

        <p class="abm-vacio">Sin resultados.</p>

    <?php else: ?>

        <div class="abm-tabla-scroll">
            <table class="abm-tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Entrega</th>
                        <th>Productos</th>
                        <th>Estado</th>
                        <th class="abm-col-acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td class="abm-codigo">#<?= e((string) $pedido['id_pedido']) ?></td>
                            <td><?= e($pedido['cliente']) ?></td>
                            <td>
                                <?= e($pedido['tipo_pedido']) ?>
                                <?php if ((bool) $pedido['confirmado_fuera_de_anticipacion']): ?>
                                    <span class="abm-descripcion">confirmado fuera de plazo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) $pedido['fecha_entrega']))) ?></td>
                            <td><?= e((string) $pedido['cantidad_productos']) ?></td>
                            <td>
                                <?php
                                $claseEstadoBadge = match ($pedido['estado']) {
                                    'Nuevo'          => 'abm-badge-nuevo',
                                    'Confirmado'     => 'abm-badge-confirmado',
                                    'En preparación' => 'abm-badge-preparacion',
                                    'Listo'          => 'abm-badge-listo',
                                    'Entregado'      => 'abm-badge-entregado',
                                    'Cancelado'      => 'abm-badge-cancelado',
                                    default          => 'abm-badge-activo',
                                };
                                ?>
                                <span class="abm-badge <?= e($claseEstadoBadge) ?>">
                                    <?= e($pedido['estado']) ?>
                                </span>
                            </td>
                            <td class="abm-col-acciones">
                                <div class="abm-acciones">
                                <?php if ($puedeEditar): ?>
                                        <a class="abm-icono" title="Editar"
                                           href="<?= e(base_url('pedido/' . $pedido['id_pedido'] . '/editar')) ?>">
                                            <span class="abm-sr">Editar pedido #<?= e((string) $pedido['id_pedido']) ?></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                            </svg>
                                        </a>
                                <?php else: ?>
                                        <a class="abm-icono" title="Ver detalle"
                                           href="<?= e(base_url('pedido/' . $pedido['id_pedido'] . '/editar')) ?>">
                                            <span class="abm-sr">Ver detalle del pedido #<?= e((string) $pedido['id_pedido']) ?></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>
