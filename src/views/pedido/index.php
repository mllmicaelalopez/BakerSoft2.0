<?php

declare(strict_types=1);

/**
 * BakerSoft - Listado de pedidos (HU-09 alta, HU-10 modificación, HU-11 consulta).
 *
 * Filtros por cliente, tipo de pedido, estado y rango de fecha de entrega.
 * A diferencia de Producto/Tipo de Producto (que recargan la página entera al
 * filtrar), acá el formulario lo intercepta assets/js/app.js: hace un fetch a
 * esta misma ruta con los filtros como query string y reemplaza el contenido
 * de #pedido-tabla-contenedor con la respuesta (pedido/_tabla.php, sin
 * layout). Si JS no llega a correr, el <form> es un GET normal y funciona
 * igual que los otros filtros del sistema, con recarga completa.
 *
 * @var array<int, array<string, mixed>> $pedidos
 * @var array<int, array<string, mixed>> $tiposPedido
 * @var array<int, string> $estados
 * @var string $filtroCliente
 * @var string $filtroTipo
 * @var string $filtroEstado
 * @var string $filtroDesde
 * @var string $filtroHasta
 */

$flash = flash_get();
?>

<?php if ($flash !== null): ?>
    <p class="abm-alerta abm-alerta-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensaje']) ?></p>
<?php endif; ?>

<div class="abm-cab">
    <p class="abm-cab-texto">Pedidos programados para preparación y entrega.</p>
    <?php if (in_array(usuario_actual()['rol'] ?? '', ['administrador', 'cajero'], true)): ?>
        <a class="abm-btn" href="<?= e(base_url('pedido/nuevo')) ?>">Nuevo pedido</a>
    <?php endif; ?>
</div>

<form class="abm-filtros" method="get" action="<?= e(base_url('pedido')) ?>" id="form-filtros-pedido">
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

    <!--
        Siempre visible, a diferencia de Producto/Tipo de Producto (que lo
        muestran solo si hay un filtro aplicado): acá los filtros se aplican
        por fetch (ver assets/js/app.js), así que este formulario no se vuelve
        a renderizar entre un filtro y otro, y "si hay filtros aplicados" dejó
        de ser algo que el PHP de esta vista pueda saber en el momento.
    -->
    <a class="abm-link" href="<?= e(base_url('pedido')) ?>">Limpiar</a>
</form>

<div id="pedido-tabla-contenedor">
    <?php require BASE_PATH . '/src/views/pedido/_tabla.php'; ?>
</div>
