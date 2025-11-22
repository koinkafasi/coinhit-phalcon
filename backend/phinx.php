<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/app/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/app/migrations/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinx_migrations',
        'default_environment' => 'development',
        'production' => [
            'adapter' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'postgres',
            'name' => $_ENV['DB_NAME'] ?? 'tahmin1x2',
            'user' => $_ENV['DB_USER'] ?? 'tahmin1x2',
            'pass' => $_ENV['DB_PASSWORD'] ?? 'tahmin1x2',
            'port' => $_ENV['DB_PORT'] ?? 5432,
            'charset' => 'utf8',
        ],
        'development' => [
            'adapter' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'postgres',
            'name' => $_ENV['DB_NAME'] ?? 'tahmin1x2',
            'user' => $_ENV['DB_USER'] ?? 'tahmin1x2',
            'pass' => $_ENV['DB_PASSWORD'] ?? 'tahmin1x2',
            'port' => $_ENV['DB_PORT'] ?? 5432,
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation'
];
