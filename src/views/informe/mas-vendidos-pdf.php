<?php

declare(strict_types=1);

/**
 * BakerSoft - Plantilla del PDF del informe de productos más vendidos (HU-14).
 *
 * La renderiza InformeController::masVendidosPdf() a un string (ob_start() +
 * view()) y se la pasa a Dompdf. Muestra exactamente los mismos datos que la
 * pantalla (informe/mas-vendidos.php): vienen del mismo armarInforme(). El
 * gráfico no va en el PDF.
 *
 * Es un documento HTML completo y con estilos propios (Dompdf no usa
 * assets/css/app.css). Usa tablas y no grillas o flex, que Dompdf no
 * interpreta bien, y fuente DejaVu Sans para que salgan los acentos y los
 * símbolos ▲ ▼ de las variaciones.
 *
 * @var array<string, mixed> $informe
 */

$comparando = (bool) $informe['comparar'];
$ind        = $informe['indicadores'];

// Mismo formato que la pantalla: "▲ +3 (+27 %)", "▼ -1 (-10 %)", "= 0 (0 %)" o
// "▲ +5 (Nuevo)".
$htmlVariacion = static function (array $variacion): string {
    $color   = ['sube' => '#2f7d4f', 'baja' => '#c0392b', 'igual' => '#7c7168'][$variacion['tipo']];
    $detalle = $variacion['nuevo']
        ? '<span style="background: #f6e0c8; color: #6d3a12; font-size: 8px; padding: 1px 4px;">Nuevo</span>'
        : e($variacion['porcentaje']);

    return '<span style="color: ' . $color . '; font-weight: bold;">'
        . e($variacion['flecha']) . ' ' . e($variacion['absoluta']) . ' (' . $detalle . ')'
        . '</span>';
};

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos más vendidos - BakerSoft</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #3a2a1c; }
        h1 { margin: 0 0 4px; font-size: 18px; color: #6d3a12; }
        p { margin: 0 0 3px; }
        .meta { color: #8a5a28; }
        .indicadores { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 12px -6px; }
        .indicador { width: 33%; border: 1px solid #ece3d8; padding: 8px; vertical-align: top; }
        .indicador-titulo { font-size: 8px; color: #9c7757; text-transform: uppercase; }
        .indicador-valor { font-size: 18px; font-weight: bold; color: #6d3a12; margin: 3px 0; }
        .indicador-texto { font-size: 12px; }
        .indicador-detalle { font-size: 8px; color: #8a5a28; }
        table.ranking { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.ranking th { background: #fdfbf8; border-bottom: 1px solid #ece3d8; padding: 5px 4px; text-align: left; font-size: 8px; color: #9c7757; text-transform: uppercase; }
        table.ranking td { border-bottom: 1px solid #f5efe7; padding: 5px 4px; }
        .derecha { text-align: right; }
        .vacio { margin-top: 18px; text-align: center; color: #9c7757; font-size: 11px; }
    </style>
</head>
<body>

    <h1>Productos más vendidos - BakerSoft</h1>

    <p class="meta">
        <?= e($informe['texto_periodo']) ?><?php if ($comparando): ?> &middot; <?= e($informe['texto_comparado']) ?><?php endif; ?>
    </p>
    <p class="meta">Filtros: <?= $informe['categoria'] !== null ? 'Categoría: ' . e($informe['categoria']) : 'Todas las categorías' ?></p>
    <p class="meta"><?= e($informe['generado']) ?></p>

    <table class="indicadores">
        <tr>
            <td class="indicador">
                <p class="indicador-titulo">Pedidos entregados</p>
                <p class="indicador-valor"><?= e((string) $ind['entregados']['valor']) ?></p>
                <?php if ($ind['entregados']['variacion'] !== null): ?>
                    <p class="indicador-detalle"><?= $htmlVariacion($ind['entregados']['variacion']) ?></p>
                    <p class="indicador-detalle">Período anterior: <?= e((string) $ind['entregados']['anterior']) ?></p>
                <?php endif; ?>
            </td>
            <td class="indicador">
                <p class="indicador-titulo">Productos distintos vendidos</p>
                <p class="indicador-valor"><?= e((string) $ind['distintos']['valor']) ?></p>
                <p class="indicador-detalle">de <?= e((string) $ind['distintos']['activos']) ?> productos activos</p>
            </td>
            <td class="indicador">
                <p class="indicador-titulo">Producto más vendido</p>
                <p class="indicador-valor indicador-texto"><?= e($ind['mas_vendido']['nombre']) ?></p>
                <?php if ($ind['mas_vendido']['detalle'] !== ''): ?>
                    <p class="indicador-detalle"><?= e($ind['mas_vendido']['detalle']) ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <?php if (!$informe['hay_datos']): ?>

        <p class="vacio">No hay pedidos entregados en el período seleccionado.</p>

    <?php else: ?>

        <table class="ranking">
            <thead>
                <tr>
                    <th>Pos.</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="derecha">Pedidos</th>
                    <th class="derecha">Cantidad total vendida</th>
                    <th class="derecha">% de pedidos</th>
                    <?php if ($comparando): ?>
                        <th class="derecha">Período anterior</th>
                        <th class="derecha">Variación</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($informe['filas'] as $fila): ?>
                    <tr>
                        <td><?= e((string) $fila['posicion']) ?></td>
                        <td><?= e($fila['producto']) ?></td>
                        <td><?= e($fila['categoria']) ?></td>
                        <td class="derecha"><?= e((string) $fila['pedidos']) ?></td>
                        <td class="derecha"><?= e($fila['total']) ?></td>
                        <td class="derecha"><?= e((string) $fila['porcentaje']) ?> %</td>
                        <?php if ($comparando): ?>
                            <td class="derecha"><?= e((string) $fila['anterior']) ?></td>
                            <td class="derecha"><?= $htmlVariacion($fila['variacion']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

</body>
</html>
