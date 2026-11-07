<?php

declare(strict_types=1);

/**
 * BakerSoft - Dashboard (HU-00). Slot del app-layout.
 *
 * Dos bloques son datos reales: las próximas entregas (HU-13), de
 * Pedido::listarProximasEntregas(), y los más vendidos (HU-14), de
 * Pedido::rankingMasVendidos() con "Últimos 7 días". El de más vendidos solo se
 * dibuja para los roles con permiso sobre el informe: para cajero y repartidor
 * $masVendidos es null y el bloque no existe. Ventas y stock siguen siendo
 * mock, armados desde DashboardController: los reales son de sprints
 * posteriores.
 *
 * @var array{etiquetas: string[], valores: int[]}       $ventas
 * @var array<int, array{nombre: string, cantidad: string}>|null $masVendidos
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

    <!-- 2. Más vendidos (solo roles con permiso sobre el informe, HU-14) -->
    <?php if ($masVendidos !== null): ?>
        <section class="dash-bloque">
            <div class="dash-bloque-cab">
                <div>
                    <h2 class="dash-bloque-titulo">Más vendidos...</h2>
                    <p class="dash-bloque-sub">Últimos 7 días</p>
                </div>
                <a class="dash-bloque-link" href="<?= e(base_url('informes/mas-vendidos?periodo=ultimos-7')) ?>">Ver más...</a>
            </div>

            <?php if ($masVendidos === []): ?>

                <p class="abm-vacio">No hay pedidos entregados en los últimos 7 días.</p>

            <?php else: ?>

                <ul class="dash-lista">
                    <?php foreach ($masVendidos as $producto): ?>
                        <li>
                            <span>&ndash; <?= e($producto['nombre']) ?></span>
                            <span class="dash-lista-cantidad"><?= e($producto['cantidad']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php endif; ?>
        </section>
    <?php endif; ?>

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
