<?php

use Phalcon\Config\Config;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

return new Config([
    'database' => [
        'adapter'  => 'Postgresql',
        'host'     => $_ENV['DB_HOST'] ?? 'postgres',
        'username' => $_ENV['DB_USER'] ?? 'tahmin1x2',
        'password' => $_ENV['DB_PASSWORD'] ?? 'tahmin1x2',
        'dbname'   => $_ENV['DB_NAME'] ?? 'tahmin1x2',
        'port'     => $_ENV['DB_PORT'] ?? 5432,
        'charset'  => 'utf8',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],

    'redis' => [
        'host'       => $_ENV['REDIS_HOST'] ?? 'redis',
        'port'       => $_ENV['REDIS_PORT'] ?? 6379,
        'index'      => $_ENV['REDIS_DB'] ?? 0,
        'persistent' => false,
        'auth'       => $_ENV['REDIS_PASSWORD'] ?? null,
    ],

    'application' => [
        'baseUri'        => $_ENV['APP_BASE_URI'] ?? '/',
        'publicUrl'      => $_ENV['APP_PUBLIC_URL'] ?? 'https://api.tahmin1x2.com',
        'controllersDir' => APP_PATH . '/controllers/',
        'modelsDir'      => APP_PATH . '/models/',
        'servicesDir'    => APP_PATH . '/services/',
        'middlewareDir'  => APP_PATH . '/middleware/',
        'migrationsDir'  => APP_PATH . '/migrations/',
        'logsDir'        => BASE_PATH . '/storage/logs/',
        'cacheDir'       => BASE_PATH . '/storage/cache/',
    ],

    'jwt' => [
        'secret'                => $_ENV['JWT_SECRET'] ?? 'change-this-secret-key-in-production',
        'algorithm'             => 'HS256',
        'access_token_expire'   => 3600, // 1 hour
        'refresh_token_expire'  => 604800, // 7 days
        'issuer'                => 'tahmin1x2.com',
    ],

    'cors' => [
        'allowedOrigins' => explode(',', $_ENV['CORS_ORIGINS'] ?? 'http://localhost:3000,https://tahmin1x2.com'),
        'allowedHeaders' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowCredentials' => true,
    ],

    'app' => [
        'env'           => $_ENV['APP_ENV'] ?? 'production',
        'debug'         => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'timezone'      => 'Europe/Istanbul',
        'locale'        => 'tr_TR',
        'logLevel'      => $_ENV['LOG_LEVEL'] ?? 'info',
    ],

    'security' => [
        'argon2' => [
            'memoryCost' => 65536,
            'timeCost'   => 4,
            'threads'    => 2,
        ],
        'workFactor' => 12,
    ],

    's3' => [
        'key'      => $_ENV['MINIO_ACCESS_KEY'] ?? 'minioadmin',
        'secret'   => $_ENV['MINIO_SECRET_KEY'] ?? 'minioadmin',
        'region'   => 'us-east-1',
        'bucket'   => $_ENV['MINIO_BUCKET'] ?? 'tahmin1x2',
        'endpoint' => $_ENV['MINIO_ENDPOINT'] ?? 'http://minio:9000',
        'version'  => 'latest',
    ],

    'api' => [
        'football' => [
            'api_key' => $_ENV['API_FOOTBALL_KEY'] ?? 'c9628c4b640448365f513088a3746750',
            'base_url' => 'https://v3.football.api-sports.io',
        ],
        'football_data' => [
            'token' => $_ENV['FOOTBALL_DATA_API_TOKEN'] ?? '7d57ea5de46e41769162d4e4f83673b7',
            'base_url' => 'https://api.football-data.org/v4',
        ],
    ],

    'cache' => [
        'lifetime' => 3600, // 1 hour
        'prefix'   => 'tahmin_',
    ],

    'pagination' => [
        'limit' => 20,
        'maxLimit' => 100,
    ],
]);
