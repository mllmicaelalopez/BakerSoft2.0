<?php

declare(strict_types=1);

/**
 * BakerSoft - Dashboard (HU-00). Slot del app-layout.
 *
 * Las próximas entregas son datos reales (HU-13): vienen de
 * Pedido::listarProximasEntregas(), la misma consulta del listado de entregas.
 * Ventas, más vendidos y stock siguen siendo mock, armados desde
 * DashboardController: los reales son de sprints posteriores.
 *
 * @var array{etiquetas: string[], valores: int[]}       $ventas
 * @var array<int, array{nombre: string, cantidad: string}> $masVendidos
 * @var array{alerta: string, bajo_stock: int, categorias: int, pedidos_pendientes: int} $stock
 * @var array<int, array<string, mixed>>                 $proximasEntregas
 */

?>

<div class="dash-grid">

    <!-- 1. Ventas -->
    <section class="dash-bloque">
        <div class="dash-bloque-cab">
            <div>
                <h2 class="dash-bloque-titulo">Ventas</h2>
                <p class="dash-bloque-sub">Resumen semanal</p>
            </div>
        </div>

        <div class="dash-placeholder">
            El gráfico de ventas se agrega cuando exista el módulo de ventas.
        </div>

        <div class="dash-etiquetas">
            <?php foreach ($ventas['etiquetas'] as $indice => $etiqueta): ?>
                <span class="dash-etiqueta"><?= e($etiqueta) ?>: <strong><?= e((string) ($ventas['valores'][$indice] ?? 0)) ?></strong></span>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 2. Más vendidos -->
    <section class="dash-bloque">
        <div class="dash-bloque-cab">
            <div>
                <h2 class="dash-bloque-titulo">Más vendidos...</h2>
                <p class="dash-bloque-sub">Últimos 7 días</p>
            </div>
        </div>

        <ul class="dash-lista">
            <?php foreach ($masVendidos as $producto): ?>
                <li>
                    <span>&ndash; <?= e($producto['nombre']) ?></span>
                    <span class="dash-lista-cantidad"><?= e($producto['cantidad']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- 3. Stock -->
    <section class="dash-bloque">
        <div class="dash-bloque-cab">
            <div>
                <h2 class="dash-bloque-titulo">Stock</h2>
            </div>
            <span class="dash-bloque-meta">Última actualización: hoy</span>
        </div>

        <p class="dash-alerta">
            <span class="dash-badge"><?= e((string) $stock['bajo_stock']) ?></span>
            <?= e($stock['alerta']) ?>
        </p>

        <div class="dash-datos">
            <span class="dash-dato">Categorías: <strong><?= e((string) $stock['categorias']) ?></strong></span>
            <span class="dash-dato">Pedidos pendientes: <strong><?= e((string) $stock['pedidos_pendientes']) ?></strong></span>
        </div>
    </section>

    <!-- 4. Próximas entregas -->
    <section class="dash-bloque">
        <div class="dash-bloque-cab">
            <div>
                <h2 class="dash-bloque-titulo">Próximas entregas...</h2>
            </div>
            <a class="dash-bloque-link" href="<?= e(base_url('entregas')) ?>">Ver más...</a>
        </div>

        <?php if ($proximasEntregas === []): ?>

            <p class="abm-vacio">No hay entregas programadas para los próximos 7 días.</p>

        <?php else: ?>

            <div class="dash-tabla-scroll">
                <table class="dash-tabla">
                    <thead>
                        <tr>
                            <th>Entrega</th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Productos</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proximasEntregas as $entrega): ?>
                            <tr>
                                <td><?= e(date('d/m H:i', strtotime((string) $entrega['fecha_entrega']))) ?></td>
                                <td><?= e($entrega['cliente']) ?></td>
                                <td><?= e(($entrega['telefono'] ?? '') !== '' ? $entrega['telefono'] : '—') ?></td>
                                <td><?= e(($entrega['productos'] ?? '') !== '' ? $entrega['productos'] : '—') ?></td>
                                <td>
                                    <?php
                                    $claseEstadoBadge = match ($entrega['estado']) {
                                        'Nuevo'          => 'abm-badge-nuevo',
                                        'Confirmado'     => 'abm-badge-confirmado',
                                        'En preparación' => 'abm-badge-preparacion',
                                        'Listo'          => 'abm-badge-listo',
                                        'Entregado'      => 'abm-badge-entregado',
                                        'Cancelado'      => 'abm-badge-cancelado',
                                        default          => 'abm-badge-activo',
                                    };
                                    ?>
                                    <span class="abm-badge <?= e($claseEstadoBadge) ?>"><?= e($entrega['estado']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

</div>
