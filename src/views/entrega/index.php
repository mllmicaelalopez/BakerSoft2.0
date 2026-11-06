<?php

declare(strict_types=1);

/**
 * BakerSoft - Listado de próximas entregas (HU-13, Sprint 5).
 *
 * Pedidos "Confirmado", "En preparación" o "Listo" a entregar en el rango de
 * fechas (por defecto, de hoy a hoy + 7), más los atrasados, ordenados por
 * fecha y hora de entrega, con indicador de urgencia, total, paginación y
 * exportación a CSV.
 *
 * A diferencia de pedido/index.php (que filtra por fetch), acá los filtros
 * son un GET normal con recarga completa, igual que Producto y Tipo de
 * Producto: la paginación y el botón de exportar dependen de la query string,
 * y con un GET normal todo queda en la URL y funciona solo. El único JS es el
 * de assets/js/app.js que avisa, antes de enviar, que "desde" no puede ser
 * posterior a "hasta".
 *
 * Con un error en las fechas (400) la pantalla se muestra sin listado: solo
 * los filtros con lo que escribió el usuario y el mensaje de error.
 *
 * @var array<int, array<string, mixed>> $entregas
 * @var array<int, array<string, mixed>> $tiposPedido
 * @var array<int, string> $estados
 * @var string $filtroCliente
 * @var string $filtroTipo
 * @var string $filtroEstado
 * @var string $filtroDesde
 * @var string $filtroHasta
 * @var string $error         Mensaje de error de fechas, o '' si no hubo.
 * @var int    $total         Total de resultados de los filtros (no solo los de la página).
 * @var int    $pagina
 * @var int    $totalPaginas
 * @var string $queryFiltros  Query string de los filtros aplicados, sin "pagina".
 */

$urlPagina = static fn (int $numero): string => base_url('entregas') . '?' . $queryFiltros . '&pagina=' . $numero;

?>

<div class="abm-cab">
    <p class="abm-cab-texto">Pedidos confirmados a entregar, ordenados por fecha y hora de entrega.</p>
    <?php if ($error === '' && $total > 0): ?>
        <a class="abm-btn-secundario" href="<?= e(base_url('entregas/exportar') . '?' . $queryFiltros) ?>">Exportar CSV</a>
    <?php endif; ?>
</div>

<p class="abm-alerta abm-alerta-error" id="error-filtros-entregas" role="alert" <?= $error === '' ? 'hidden' : '' ?>><?= e($error) ?></p>

<form class="abm-filtros" method="get" action="<?= e(base_url('entregas')) ?>" id="form-filtros-entregas">
    <div class="abm-buscador">
        <svg class="abm-buscador-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-3.5-3.5"></path>
        </svg>
        <input class="abm-buscador-input" type="search" name="cliente"
               value="<?= e($filtroCliente) ?>" placeholder="Buscar por cliente">
    </div>

    <select class="abm-select" name="tipo" aria-label="Filtrar por tipo de pedido">
        <option value="">Todos los tipos</option>
        <?php foreach ($tiposPedido as $tp): ?>
            <option value="<?= e((string) $tp['id_tipo_pedido']) ?>"
                <?= $filtroTipo === (string) $tp['id_tipo_pedido'] ? 'selected' : '' ?>>
                <?= e($tp['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="abm-select" name="estado" aria-label="Filtrar por estado">
        <option value="">Todos los estados</option>
        <?php foreach ($estados as $est): ?>
            <option value="<?= e($est) ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= e($est) ?></option>
        <?php endforeach; ?>
    </select>

    <input class="abm-select" type="date" name="fecha_desde" value="<?= e($filtroDesde) ?>" aria-label="Entrega desde">
    <input class="abm-select" type="date" name="fecha_hasta" value="<?= e($filtroHasta) ?>" aria-label="Entrega hasta">

    <button class="abm-btn-secundario" type="submit">Filtrar</button>

    <a class="abm-link" href="<?= e(base_url('entregas')) ?>">Limpiar filtros</a>
</form>

<?php if ($error === ''): ?>

    <p class="abm-total"><?= e((string) $total) ?> <?= $total === 1 ? 'entrega encontrada' : 'entregas encontradas' ?></p>

    <div class="abm-tabla-caja">
        <?php if ($entregas === []): ?>

            <p class="abm-vacio">No hay entregas programadas para los filtros seleccionados.</p>

        <?php else: ?>

            <div class="abm-tabla-scroll">
                <table class="abm-tabla">
                    <thead>
                        <tr>
                            <th>N° de pedido</th>
                            <th>Fecha y hora de entrega</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Tipo de pedido</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Urgencia</th>
                            <th class="abm-col-acciones">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entregas as $entrega): ?>
                            <tr>
                                <td class="abm-codigo">#<?= e((string) $entrega['id_pedido']) ?></td>
                                <td class="abm-precio"><?= e(date('d/m/Y H:i', strtotime((string) $entrega['fecha_entrega']))) ?></td>
                                <td><?= e($entrega['cliente']) ?></td>
                                <td><?= e(($entrega['telefono'] ?? '') !== '' ? $entrega['telefono'] : '—') ?></td>
                                <td><?= e(($entrega['direccion'] ?? '') !== '' ? $entrega['direccion'] : '—') ?></td>
                                <td><?= e($entrega['tipo_pedido']) ?></td>
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
                                    <span class="abm-badge <?= e($claseEstadoBadge) ?>">
                                        <?= e($entrega['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $claseUrgencia = match ($entrega['urgencia']) {
                                        'Atrasado' => 'abm-urgencia-atrasado',
                                        'Hoy'      => 'abm-urgencia-hoy',
                                        default    => 'abm-urgencia-proximo',
                                    };
                                    ?>
                                    <span class="abm-urgencia <?= e($claseUrgencia) ?>"><?= e($entrega['urgencia']) ?></span>
                                </td>
                                <td class="abm-col-acciones">
                                    <div class="abm-acciones">
                                        <a class="abm-icono" title="Ver detalle"
                                           href="<?= e(base_url('pedido/' . $entrega['id_pedido'] . '/ver')) ?>">
                                            <span class="abm-sr">Ver detalle del pedido #<?= e((string) $entrega['id_pedido']) ?></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <nav class="abm-paginacion" aria-label="Paginación">
            <?php if ($pagina > 1): ?>
                <a class="abm-link" href="<?= e($urlPagina($pagina - 1)) ?>">Anterior</a>
            <?php else: ?>
                <span class="abm-paginacion-off">Anterior</span>
            <?php endif; ?>

            <span>Página <?= e((string) $pagina) ?> de <?= e((string) $totalPaginas) ?></span>

            <?php if ($pagina < $totalPaginas): ?>
                <a class="abm-link" href="<?= e($urlPagina($pagina + 1)) ?>">Siguiente</a>
            <?php else: ?>
                <span class="abm-paginacion-off">Siguiente</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php endif; ?>
