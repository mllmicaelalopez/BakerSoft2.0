<?php

declare(strict_types=1);

/**
 * BakerSoft - Mapa de capacidades por módulo (HT-01: Roles y Permisos).
 *
 * Cada clave es un prefijo de ruta (el mismo que usan $abms y el switch de
 * public/index.php); el valor es la lista de códigos de rol (tabla `roles`)
 * que pueden entrar. Lo consume requireRole() en src/helpers/functions.php.
 *
 * Para sumar un módulo nuevo alcanza con agregar una línea acá: no hace falta
 * tocar requireRole() ni los controladores que ya funcionan.
 */
const CAPACIDADES = [
    'producto'      => ['administrador'],
    'tipo-producto' => ['administrador'],
    'usuario'       => ['administrador'],
    'dashboard'     => ['administrador', 'cajero', 'repartidor', 'maestro_panadero'],
    // Módulo Pedidos (Sprint 4): el permiso se divide en dos claves porque el
    // conjunto de roles no es el mismo para cada acción.
    // - 'pedido': alta y modificación (HU-09, HU-10). Solo administrador y
    //   cajero pueden crear o editar un pedido.
    // - 'pedido-consulta': listado y detalle, solo lectura (HU-11). Los
    //   cuatro roles pueden consultar.
    // - Cancelación (HU-12): usa 'pedido' (administrador y cajero), porque es
    //   el mismo conjunto de roles. La restricción extra del cajero (solo sus
    //   propios pedidos, dentro de los 30 minutos) no es de rol sino de cada
    //   pedido puntual, y la resuelve PedidoController::puedeCancelar().
    'pedido'          => ['administrador', 'cajero'],
    'pedido-consulta' => ['administrador', 'cajero', 'repartidor', 'maestro_panadero'],
    // Informes (Sprint 5): el de productos más vendidos (HU-14) es información
    // de gestión (qué producir y qué comprar), no de operación del día a día:
    // solo administrador y maestro panadero. Cajero y repartidor no ven la
    // opción en el menú ni el bloque del Inicio, y el servidor responde 403 a
    // la pantalla y al PDF. Lo usa también el menú lateral (app-layout.php).
    'informe-mas-vendidos' => ['administrador', 'maestro_panadero'],
    // A futuro: 'reparto' => ['administrador', 'repartidor'], etc.
];
