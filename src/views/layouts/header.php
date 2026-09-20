<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? 'BakerSoft') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="<?= e(base_url()) ?>">
            <span class="brand-mark">BS</span>
            <span class="brand-name">BakerSoft</span>
        </a>
        <span class="topbar-tag">Sistema de gestión para panaderías</span>
    </header>
    <main class="container">
