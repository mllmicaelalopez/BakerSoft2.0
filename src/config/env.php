<?php

declare(strict_types=1);

/**
 * BakerSoft - Carga de variables de entorno desde .env (HT-02: email real).
 *
 * Sin dependencias externas a propósito: el archivo es chico y esto evita
 * sumar una librería (tipo vlucas/phpdotenv) solo para leer "CLAVE=valor".
 * Si el proyecto llega a necesitar algo más completo (variables multilínea,
 * interpolación, etc.) ahí sí conviene migrar a una librería de Composer.
 */

/**
 * Lee un archivo .env y define sus variables con putenv()/$_ENV, sin pisar
 * las que ya existan (una variable de entorno real del sistema gana siempre).
 *
 * Si el archivo no existe no hace nada: el proyecto sigue funcionando igual
 * que antes de que existiera .env (ver env() más abajo).
 */
function cargar_env(string $archivo): void
{
    if (!is_file($archivo)) {
        return;
    }

    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        if (!str_contains($linea, '=')) {
            continue;
        }

        [$clave, $valor] = explode('=', $linea, 2);

        $clave = trim($clave);
        $valor = trim($valor);

        // Comillas simples o dobles alrededor del valor son opcionales.
        if (strlen($valor) >= 2
            && (($valor[0] === '"' && str_ends_with($valor, '"'))
                || ($valor[0] === "'" && str_ends_with($valor, "'")))) {
            $valor = substr($valor, 1, -1);
        }

        if ($clave === '' || getenv($clave) !== false) {
            continue;
        }

        putenv($clave . '=' . $valor);
        $_ENV[$clave] = $valor;
    }
}

/**
 * Lee una variable de entorno (cargada desde .env o del sistema).
 */
function env(string $clave, ?string $default = null): ?string
{
    $valor = getenv($clave);

    return $valor !== false ? $valor : $default;
}
