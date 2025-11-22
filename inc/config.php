<?php
// Configuración básica. Usa variables de entorno cuando estén disponibles.
return [
    'db_host' => getenv('MYSQL_HOST') ?: 'db',
    'db_name' => getenv('MYSQL_DATABASE') ?: 'classicdb',
    'db_user' => getenv('MYSQL_USER') ?: 'classicuser',
    'db_pass' => getenv('MYSQL_PASSWORD') ?: 'classicpass123',
    'db_port' => getenv('MYSQL_PORT') ?: 3306,
    'master_key' => 'gatitos2025',
];
