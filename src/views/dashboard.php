<?php

declare(strict_types=1);

/**
 * BakerSoft - Dashboard (HU-00). Slot del app-layout.
 *
 * Los datos son mock: vienen armados desde DashboardController. Las ventas,
 * el stock y las entregas reales son de sprints posteriores.
 *
 * @var array{etiquetas: string[], valores: int[]}       $ventas
 * @var array<int, array{nombre: string, cantidad: string}> $masVendidos
 * @var array{alerta: string, bajo_stock: int, categorias: int, pedidos_pendientes: int} $stock
 * @var array<int, array<string, string>>                $proximasEntregas
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
            <a class="dash-bloque-link" href="<?= e(base_url('pedidos')) ?>">Ver más...</a>
        </div>

        <div class="dash-tabla-scroll">
            <table class="dash-tabla">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Pedidos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proximasEntregas as $entrega): ?>
                        <tr>
                            <td><?= e($entrega['cliente']) ?></td>
                            <td><?= e($entrega['contacto']) ?></td>
                            <td><?= e($entrega['producto']) ?></td>
                            <td><?= e($entrega['cantidad']) ?></td>
                            <td><?= e($entrega['pedidos']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
