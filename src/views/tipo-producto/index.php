<?php

declare(strict_types=1);

/**
 * BakerSoft - Listado de tipos de producto (HU-05 y HU-08).
 *
 * @var array<int, array<string, mixed>> $tipos
 * @var string $buscar
 * @var string $estado
 */

$flash = flash_get();

?>

<?php if ($flash !== null): ?>
    <p class="abm-alerta abm-alerta-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensaje']) ?></p>
<?php endif; ?>

<div class="abm-cab">
    <p class="abm-cab-texto">Categorías con las que se clasifican los productos.</p>
    <a class="abm-btn" href="<?= e(base_url('tipo-producto/nuevo')) ?>">Nuevo tipo de producto</a>
</div>

<form class="abm-filtros" method="get" action="<?= e(base_url('tipo-producto')) ?>">
    <div class="abm-buscador">
        <svg class="abm-buscador-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-3.5-3.5"></path>
        </svg>
        <input class="abm-buscador-input" type="search" name="buscar"
               value="<?= e($buscar) ?>" placeholder="Buscar por nombre">
    </div>

    <select class="abm-select" name="estado" aria-label="Filtrar por estado">
        <option value=""         <?= $estado === ''         ? 'selected' : '' ?>>Todos</option>
        <option value="activo"   <?= $estado === 'activo'   ? 'selected' : '' ?>>Activos</option>
        <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
    </select>

    <button class="abm-btn-secundario" type="submit">Filtrar</button>

    <?php if ($buscar !== '' || $estado !== ''): ?>
        <a class="abm-link" href="<?= e(base_url('tipo-producto')) ?>">Limpiar</a>
    <?php endif; ?>
</form>

<div class="abm-tabla-caja">
    <?php if ($tipos === []): ?>

        <p class="abm-vacio">Sin resultados.</p>

    <?php else: ?>

        <div class="abm-tabla-scroll">
            <table class="abm-tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th class="abm-col-acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tipos as $tipo): ?>
                        <?php $activo = (bool) $tipo['activo']; ?>
                        <tr>
                            <td class="abm-codigo"><?= e($tipo['codigo']) ?></td>
                            <td>
                                <?= e($tipo['nombre']) ?>
                                <?php if (($tipo['descripcion'] ?? '') !== ''): ?>
                                    <span class="abm-descripcion"><?= e($tipo['descripcion']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="abm-badge <?= $activo ? 'abm-badge-activo' : 'abm-badge-inactivo' ?>">
                                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="abm-col-acciones">
                                <div class="abm-acciones">

                                    <a class="abm-icono" title="Editar"
                                       href="<?= e(base_url('tipo-producto/' . $tipo['id_tipo_producto'] . '/editar')) ?>">
                                        <span class="abm-sr">Editar <?= e($tipo['nombre']) ?></span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </a>

                                    <!--
                                        OJO: este botón NO borra nada. Hace baja lógica:
                                        alterna el campo `activo` del registro. La fila
                                        sigue existiendo siempre en la base.
                                    -->
                                    <form method="post"
                                          action="<?= e(base_url('tipo-producto/' . $tipo['id_tipo_producto'] . '/estado')) ?>">
                                        <button class="abm-icono <?= $activo ? 'abm-icono-apagar' : 'abm-icono-prender' ?>"
                                                type="submit"
                                                title="<?= $activo ? 'Desactivar (no borra)' : 'Activar' ?>">
                                            <span class="abm-sr"><?= $activo ? 'Desactivar' : 'Activar' ?> <?= e($tipo['nombre']) ?></span>
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
