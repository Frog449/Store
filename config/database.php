<?php
declare(strict_types=1);

return [
    'host'     => getenv('STORE_DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('STORE_DB_PORT') ?: '3306',
    'database' => getenv('STORE_DB_NAME') ?: 'store',
    'username' => getenv('STORE_DB_USER') ?: 'root',
    'password' => getenv('STORE_DB_PASSWORD') ?: '',
    'ssl'      => filter_var(getenv('STORE_DB_SSL'), FILTER_VALIDATE_BOOLEAN),
];
