<?php

declare(strict_types=1);

/**
 * BakerSoft - Informe de productos más vendidos (HU-14, Sprint 5).
 *
 * Ranking de los productos de los pedidos "Entregado" del período, con tres
 * indicadores, gráfico de barras de los 10 primeros, tabla completa y, si se
 * pide, comparativo con el período anterior. Los números ya vienen armados y
 * formateados desde InformeController::armarInforme() (el mismo que usa el
 * PDF): esta vista solo los muestra.
 *
 * Como en entrega/index.php, los parámetros son un GET normal con recarga
 * completa, y el único JS (assets/js/app.js) habilita las fechas solo con
 * "Rango personalizado" y avisa antes de enviar si faltan o están al revés.
 *
 * Con parámetros inválidos (400) la pantalla se muestra sin informe: el panel
 * con lo que escribió el usuario y el mensaje de validación, nunca el código.
 *
 * @var array<string, string> $periodos        Clave => texto del select de período.
 * @var array<int, array<string, mixed>> $categorias
 * @var string $periodo
 * @var string $filtroDesde
 * @var string $filtroHasta
 * @var string $filtroCategoria
 * @var bool   $comparar
 * @var string $error          Mensaje de validación, o '' si no hubo.
 * @var array<string, mixed>|null $informe   Null si hubo error.
 * @var string $queryPdf       Query string de los parámetros efectivos, para el PDF.
 */

// "▲ +3 (+27 %)", "▼ -1 (-10 %)", "= 0 (0 %)" o "▲ +5 (Nuevo)". La flecha es
// decorativa: el texto accesible dice si subió, bajó, no cambió o es nuevo.
$htmlVariacion = static function (array $variacion): string {
    $clase   = ['sube' => 'inf-var-sube', 'baja' => 'inf-var-baja', 'igual' => 'inf-var-igual'][$variacion['tipo']];
    $detalle = $variacion['nuevo'] ? '<span class="inf-nuevo">Nuevo</span>' : e($variacion['porcentaje']);

    return '<span class="inf-var ' . $clase . '">'
        . '<span class="abm-sr">' . e($variacion['texto_accesible']) . '</span>'
        . '<span aria-hidden="true">' . e($variacion['flecha']) . '</span> '
        . e($variacion['absoluta']) . ' (' . $detalle . ')'
        . '</span>';
};

$comparando = $informe !== null && $informe['comparar'];

?>

<div class="abm-cab">
    <p class="abm-cab-texto">Ranking de productos según los pedidos entregados en el período.</p>
    <?php if ($informe !== null && $informe['hay_datos']): ?>
        <a class="abm-btn-secundario" href="<?= e(base_url('informes/mas-vendidos/pdf') . '?' . $queryPdf) ?>">Descargar PDF</a>
    <?php endif; ?>
</div>

<p class="abm-alerta abm-alerta-error" id="error-informe-mas-vendidos" role="alert" <?= $error === '' ? 'hidden' : '' ?>><?= e($error) ?></p>

<form class="abm-filtros" method="get" action="<?= e(base_url('informes/mas-vendidos')) ?>" id="form-informe-mas-vendidos">
    <select class="abm-select" name="periodo" aria-label="Período">
        <?php foreach ($periodos as $clave => $texto): ?>
            <option value="<?= e($clave) ?>" <?= $periodo === $clave ? 'selected' : '' ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
    </select>

    <input class="abm-select" type="date" name="fecha_desde" value="<?= e($filtroDesde) ?>" aria-label="Desde"
           <?= $periodo !== 'personalizado' ? 'disabled' : '' ?>>
    <input class="abm-select" type="date" name="fecha_hasta" value="<?= e($filtroHasta) ?>" aria-label="Hasta"
           <?= $periodo !== 'personalizado' ? 'disabled' : '' ?>>

    <select class="abm-select" name="categoria" aria-label="Categoría de producto">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= e((string) $cat['id_tipo_producto']) ?>"
                <?= $filtroCategoria === (string) $cat['id_tipo_producto'] ? 'selected' : '' ?>>
                <?= e($cat['nombre']) ?><?= (bool) $cat['activo'] ? '' : ' (inactiva)' ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="abm-btn-secundario" type="submit">Generar</button>

    <label class="inf-comparar">
        <input type="checkbox" name="comparar" value="1" <?= $comparar ? 'checked' : '' ?>>
        Comparar con el período anterior
    </label>
</form>

<?php if ($informe !== null): ?>

    <div class="inf-periodo">
        <p>
            <?= e($informe['texto_periodo']) ?>
            <?php if ($comparando): ?>
                &middot; <?= e($informe['texto_comparado']) ?>
            <?php endif; ?>
        </p>
        <p><?= e($informe['generado']) ?></p>
    </div>

    <?php
    $ind = $informe['indicadores'];
    $ayudaEntregados = 'Cantidad de pedidos en estado Entregado con fecha de entrega dentro del período';
    $ayudaDistintos  = 'Cantidad de productos diferentes que aparecen en al menos un pedido entregado del período';
    $ayudaMasVendido = 'Producto que aparece en más pedidos entregados del período';
    ?>

    <div class="inf-indicadores">

        <div class="inf-indicador">
            <p class="inf-indicador-titulo">
                Pedidos entregados
                <span class="inf-ayuda" title="<?= e($ayudaEntregados) ?>" tabindex="0" aria-label="<?= e($ayudaEntregados) ?>">?</span>
            </p>
            <p class="inf-indicador-valor"><?= e((string) $ind['entregados']['valor']) ?></p>
            <?php if ($ind['entregados']['variacion'] !== null): ?>
                <p class="inf-indicador-detalle"><?= $htmlVariacion($ind['entregados']['variacion']) ?></p>
                <p class="inf-indicador-detalle">Período anterior: <?= e((string) $ind['entregados']['anterior']) ?></p>
            <?php endif; ?>
        </div>

        <div class="inf-indicador">
            <p class="inf-indicador-titulo">
                Productos distintos vendidos
                <span class="inf-ayuda" title="<?= e($ayudaDistintos) ?>" tabindex="0" aria-label="<?= e($ayudaDistintos) ?>">?</span>
            </p>
            <p class="inf-indicador-valor"><?= e((string) $ind['distintos']['valor']) ?></p>
            <p class="inf-indicador-detalle">de <?= e((string) $ind['distintos']['activos']) ?> productos activos</p>
        </div>

        <div class="inf-indicador">
            <p class="inf-indicador-titulo">
                Producto más vendido
                <span class="inf-ayuda" title="<?= e($ayudaMasVendido) ?>" tabindex="0" aria-label="<?= e($ayudaMasVendido) ?>">?</span>
            </p>
            <p class="inf-indicador-valor inf-indicador-texto"><?= e($ind['mas_vendido']['nombre']) ?></p>
            <?php if ($ind['mas_vendido']['detalle'] !== ''): ?>
                <p class="inf-indicador-detalle"><?= e($ind['mas_vendido']['detalle']) ?></p>
            <?php endif; ?>
        </div>

    </div>

    <?php if (!$informe['hay_datos']): ?>

        <div class="abm-tabla-caja">
            <p class="abm-vacio">No hay pedidos entregados en el período seleccionado.</p>
        </div>

    <?php else: ?>

        <section class="inf-grafico-caja">
            <h2 class="inf-titulo">Top 10 por cantidad de pedidos</h2>

            <?php if ($comparando): ?>
                <ul class="inf-leyenda">
                    <li><span class="inf-leyenda-punto inf-barra-actual"></span> Período actual</li>
                    <li><span class="inf-leyenda-punto inf-barra-anterior"></span> Período anterior</li>
                </ul>
            <?php endif; ?>

            <div class="inf-grafico">
                <?php foreach ($informe['grafico'] as $barra): ?>
                    <div class="inf-barra-fila">
                        <span class="inf-barra-nombre" title="<?= e($barra['producto']) ?>"><?= e($barra['producto']) ?></span>
                        <div class="inf-barras">
                            <div class="inf-barra">
                                <div class="inf-barra-actual" style="width: <?= e((string) $barra['ancho_actual']) ?>%"></div>
                                <span class="inf-barra-valor"><?= e((string) $barra['actual']) ?></span>
                            </div>
                            <?php if ($barra['anterior'] !== null): ?>
                                <div class="inf-barra">
                                    <div class="inf-barra-anterior" style="width: <?= e((string) $barra['ancho_anterior']) ?>%"></div>
                                    <span class="inf-barra-valor"><?= e((string) $barra['anterior']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="abm-tabla-caja">
            <div class="abm-tabla-scroll">
                <table class="abm-tabla">
                    <thead>
                        <tr>
                            <th>Posición</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Cantidad de pedidos</th>
                            <th>Cantidad total vendida</th>
                            <th>% de pedidos</th>
                            <?php if ($comparando): ?>
                                <th>Período anterior</th>
                                <th>Variación</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($informe['filas'] as $fila): ?>
                            <tr>
                                <td class="abm-codigo"><?= e((string) $fila['posicion']) ?></td>
                                <td><?= e($fila['producto']) ?></td>
                                <td><?= e($fila['categoria']) ?></td>
                                <td class="abm-precio"><?= e((string) $fila['pedidos']) ?></td>
                                <td class="abm-precio"><?= e($fila['total']) ?></td>
                                <td class="abm-precio"><?= e((string) $fila['porcentaje']) ?> %</td>
                                <?php if ($comparando): ?>
                                    <td class="abm-precio"><?= e((string) $fila['anterior']) ?></td>
                                    <td class="abm-precio"><?= $htmlVariacion($fila['variacion']) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>
