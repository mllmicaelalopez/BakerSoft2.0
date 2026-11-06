<?php

declare(strict_types=1);

/**
 * BakerSoft - Dashboard (HU-00).
 *
 * El bloque de próximas entregas ya es real (HU-13): usa la misma consulta
 * que el listado de EntregaController, con sus filtros por defecto y las 5
 * primeras filas. Los datos de ventas, más vendidos y stock todavía no existen
 * en la base, así que por ahora se arman acá como mock. Cuando estén los
 * módulos reales, cada bloque pasa a leer de su modelo y la vista no debería
 * cambiar.
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

            'masVendidos' => [
                ['nombre' => 'Alfajores de maicena', 'cantidad' => '220 uds.'],
                ['nombre' => 'Facturas surtidas',    'cantidad' => '185 uds.'],
                ['nombre' => 'Pan francés',          'cantidad' => '140 kg'],
                ['nombre' => 'Tortas de cumpleaños', 'cantidad' => '32 uds.'],
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
        ]);
    }
}
