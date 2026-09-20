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
 * El ícono de editar se oculta para quien no tiene permiso de alta/edición
 * (maestro_panadero, repartidor): pueden consultar el listado completo, pero
 * un link a una pantalla que igual les va a rechazar el acceso no aporta nada.
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
                                <span class="abm-badge <?= $pedido['estado'] === 'Cancelado' ? 'abm-badge-inactivo' : 'abm-badge-activo' ?>">
                                    <?= e($pedido['estado']) ?>
                                </span>
                            </td>
                            <td class="abm-col-acciones">
                                <?php if ($puedeEditar): ?>
                                    <div class="abm-acciones">
                                        <a class="abm-icono" title="Editar"
                                           href="<?= e(base_url('pedido/' . $pedido['id_pedido'] . '/editar')) ?>">
                                            <span class="abm-sr">Editar pedido #<?= e((string) $pedido['id_pedido']) ?></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>
