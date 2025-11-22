<?php
// Configuración básica. Usa variables de entorno cuando estén disponibles.

// Simple .env loader
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

return [
    'db_host' => getenv('MYSQL_HOST') ?: 'db',
    'db_name' => getenv('MYSQL_DATABASE') ?: 'db',
    'db_user' => getenv('MYSQL_USER') ?: 'user',
    'db_pass' => getenv('DB_PASS') ?: 'password',
    'db_port' => getenv('DB_PORT') ?: 3306,
    'master_key' => getenv('MASTER_KEY') ?: 'key',
    'salesman_key' => getenv('SALESMAN_KEY') ?: 'key',
];
