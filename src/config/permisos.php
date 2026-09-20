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
    // La cancelación (HU-12) todavía no tiene su propia clave definida; si
    // termina teniendo un conjunto de roles distinto de 'pedido', separarla
    // en una tercera clave en vez de reusar alguna de estas dos.
    'pedido'          => ['administrador', 'cajero'],
    'pedido-consulta' => ['administrador', 'cajero', 'repartidor', 'maestro_panadero'],
    // A futuro: 'reparto' => ['administrador', 'repartidor'], etc.
];
