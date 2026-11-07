<?php

declare(strict_types=1);

/**
 * BakerSoft - Dashboard (HU-00).
 *
 * Dos bloques ya son reales: el de próximas entregas (HU-13) usa la misma
 * consulta que el listado de EntregaController, con sus filtros por defecto y
 * las 5 primeras filas; el de más vendidos (HU-14) usa la consulta del informe
 * (Pedido::rankingMasVendidos()) con "Últimos 7 días" y las 4 primeras filas, y
 * solo se consulta y se muestra a los roles con permiso sobre el informe
 * (cajero y repartidor reciben null y la vista no lo dibuja). Los datos de
 * ventas y stock todavía no existen en la base, así que por ahora se arman acá
 * como mock. Cuando estén los módulos reales, cada bloque pasa a leer de su
 * modelo y la vista no debería cambiar.
 */
final class DashboardController
{
    /**
     * GET /dashboard
     */
    public function index(): void
    {
        requireAuth();
        requireRole('dashboard');

        // Más vendidos (HU-14): solo para los roles con permiso sobre el
        // informe; para el resto no se consulta la base y el bloque no se
        // muestra. Mismo rango que el período "Últimos 7 días" del informe.
        $masVendidos = null;

        if (in_array(usuario_actual()['rol'] ?? '', CAPACIDADES['informe-mas-vendidos'], true)) {
            $rango = InformeController::rangoUltimos7();

            $masVendidos = array_map(
                static fn (array $fila): array => [
                    'nombre'   => (string) $fila['producto'],
                    'cantidad' => formatear_cantidad((string) $fila['total'], (string) $fila['unidad_medida']),
                ],
                Pedido::rankingMasVendidos($rango['desde'], $rango['hasta'], null, 4)
            );
        }

        view('layouts/app-layout', [
            'slot'       => 'dashboard',
            'titulo'     => 'Inicio',
            'menuActivo' => 'inicio',

            // --- datos mock, a reemplazar por consultas reales cuando exista
            //     el módulo de Ventas ---
            'ventas' => [
                'etiquetas' => ['Confirmadas', 'Pendientes', 'En camino', 'Entregadas'],
                'valores'   => [48, 15, 9, 62],
            ],

            'stock' => [
                'alerta'             => 'Alerta: Bajo stock',
                'bajo_stock'         => 7,
                'categorias'         => 12,
                'pedidos_pendientes' => 5,
            ],

            // --- dato real (HU-13): las 5 primeras próximas entregas, con la
            //     misma consulta y los mismos filtros por defecto que el
            //     listado de EntregaController ---
            'proximasEntregas' => Pedido::listarProximasEntregas(EntregaController::filtrosPorDefecto(), 5),

            // --- dato real (HU-14): ver arriba; null si el rol no tiene permiso ---
            'masVendidos' => $masVendidos,
        ]);
    }
}
