<?php
// Configuración básica. Usa variables de entorno cuando estén disponibles.
return [
    'db_host' => getenv('MYSQL_HOST') ?: 'db',
    'db_name' => getenv('MYSQL_DATABASE') ?: 'classicdb',
    'db_user' => getenv('MYSQL_USER') ?: 'classicuser',
    'db_pass' => getenv('DB_PASS') ?: 'classicpass123',
    'db_port' => getenv('DB_PORT') ?: 3306,
    'master_key' => getenv('MASTER_KEY') ?: 'gatitos2025',
    'salesman_key' => getenv('SALESMAN_KEY') ?: 'salesman2025',
];
