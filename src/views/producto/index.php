<?php

declare(strict_types=1);

/**
 * BakerSoft - Listado de productos (HU-01 a HU-04 de Sprint 2).
 *
 * @var array<int, array<string, mixed>> $productos
 * @var array<int, array<string, mixed>> $categorias
 * @var string   $buscar
 * @var string   $estado
 * @var int|null $categoria
 */

$flash = flash_get();

?>

<?php if ($flash !== null): ?>
    <p class="abm-alerta abm-alerta-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensaje']) ?></p>
<?php endif; ?>

<div class="abm-cab">
    <p class="abm-cab-texto">Catálogo de productos de la panadería.</p>
    <a class="abm-btn" href="<?= e(base_url('producto/nuevo')) ?>">Nuevo Producto</a>
</div>

<form class="abm-filtros" method="get" action="<?= e(base_url('producto')) ?>">
    <div class="abm-buscador">
        <svg class="abm-buscador-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-3.5-3.5"></path>
        </svg>
        <input class="abm-buscador-input" type="search" name="buscar"
               value="<?= e($buscar) ?>" placeholder="Buscar por nombre o código">
    </div>

    <select class="abm-select" name="estado" aria-label="Filtrar por estado">
        <option value=""         <?= $estado === ''         ? 'selected' : '' ?>>Todos</option>
        <option value="activo"   <?= $estado === 'activo'   ? 'selected' : '' ?>>Activos</option>
        <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
    </select>

    <select class="abm-select" name="categoria" aria-label="Filtrar por categoría">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= e((string) $cat['id_tipo_producto']) ?>"
                <?= $categoria === (int) $cat['id_tipo_producto'] ? 'selected' : '' ?>>
                <?= e($cat['nombre']) ?><?= (bool) $cat['activo'] ? '' : ' (inactiva)' ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="abm-btn-secundario" type="submit">Filtrar</button>

    <?php if ($buscar !== '' || $estado !== '' || $categoria !== null): ?>
        <a class="abm-link" href="<?= e(base_url('producto')) ?>">Limpiar</a>
    <?php endif; ?>
</form>

<div class="abm-tabla-caja">
    <?php if ($productos === []): ?>

        <p class="abm-vacio">Sin resultados.</p>

    <?php else: ?>

        <div class="abm-tabla-scroll">
            <table class="abm-tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th class="abm-col-acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                        <?php $activo = (bool) $producto['activo']; ?>
                        <tr>
                            <td class="abm-codigo"><?= e($producto['codigo']) ?></td>
                            <td>
                                <?= e($producto['nombre']) ?>
                                <?php if (($producto['descripcion'] ?? '') !== ''): ?>
                                    <span class="abm-descripcion"><?= e($producto['descripcion']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($producto['categoria']) ?>
                                <?php if (!(bool) $producto['categoria_activa']): ?>
                                    <span class="abm-descripcion">categoría inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td class="abm-precio">$ <?= e(number_format((float) $producto['precio'], 2, ',', '.')) ?></td>
                            <td>
                                <span class="abm-badge <?= $activo ? 'abm-badge-activo' : 'abm-badge-inactivo' ?>">
                                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="abm-col-acciones">
                                <div class="abm-acciones">

                                    <a class="abm-icono" title="Editar"
                                       href="<?= e(base_url('producto/' . $producto['id_producto'] . '/editar')) ?>">
                                        <span class="abm-sr">Editar <?= e($producto['nombre']) ?></span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </a>

                                    <!--
                                        OJO: este botón NO borra nada. Hace baja lógica:
                                        alterna el campo `activo`. La fila sigue existiendo.
                                    -->
                                    <form method="post"
                                          action="<?= e(base_url('producto/' . $producto['id_producto'] . '/estado')) ?>">
                                        <button class="abm-icono <?= $activo ? 'abm-icono-apagar' : 'abm-icono-prender' ?>"
                                                type="submit"
                                                title="<?= $activo ? 'Desactivar (no borra)' : 'Activar' ?>">
                                            <span class="abm-sr"><?= $activo ? 'Desactivar' : 'Activar' ?> <?= e($producto['nombre']) ?></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                                <path d="M12 3v9"></path>
                                                <path d="M6.5 6.5a8 8 0 1 0 11 0"></path>
                                            </svg>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>
