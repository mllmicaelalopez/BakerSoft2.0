<?php

declare(strict_types=1);

/**
 * BakerSoft - Dashboard (HU-00).
 *
 * Los datos de ventas, stock y entregas todavía no existen en la base, así
 * que por ahora se arman acá como mock. Cuando estén los módulos reales, cada
 * bloque pasa a leer de su modelo y la vista no debería cambiar.
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

            'proximasEntregas' => [
                [
                    'cliente'  => 'Confitería La Esquina',
                    'contacto' => '11 4567-8901',
                    'producto' => 'Facturas surtidas',
                    'cantidad' => '12 docenas',
                    'pedidos'  => '3',
                ],
                [
                    'cliente'  => 'Café Pellegrini',
                    'contacto' => '11 2345-6789',
                    'producto' => 'Medialunas',
                    'cantidad' => '20 docenas',
                    'pedidos'  => '5',
                ],
                [
                    'cliente'  => 'Kiosco Don Pedro',
                    'contacto' => '11 9876-5432',
                    'producto' => 'Alfajores de maicena',
                    'cantidad' => '150 uds.',
                    'pedidos'  => '2',
                ],
            ],
        ]);
    }
}
