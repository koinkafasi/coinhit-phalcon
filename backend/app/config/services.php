<?php

use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Cache\AdapterFactory;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Logger\Logger;
use Phalcon\Logger\Adapter\Stream as StreamAdapter;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Predis\Client as RedisClient;

/**
 * Shared configuration service
 */
$di->setShared('config', function () {
    return include APP_PATH . "/config/config.php";
});

/**
 * Database connection
 */
$di->setShared('db', function () {
    $config = $this->getConfig();
    $dbConfig = $config->database->toArray();

    return new DbAdapter($dbConfig);
});

/**
 * Redis connection (optional)
 */
$di->setShared('redis', function () {
    try {
        $config = $this->getConfig();
        $redisConfig = $config->redis->toArray();

        $client = new RedisClient([
            'scheme' => 'tcp',
            'host'   => $redisConfig['host'],
            'port'   => $redisConfig['port'],
        ]);

        if (!empty($redisConfig['auth'])) {
            $client->auth($redisConfig['auth']);
        }

        if ($redisConfig['index'] > 0) {
            $client->select($redisConfig['index']);
        }

        return $client;
    } catch (\Exception $e) {
        // Redis is optional, return null if not available
        return null;
    }
});

/**
 * Cache service (fallback to file cache if Redis unavailable)
 */
$di->setShared('cache', function () {
    $config = $this->getConfig();

    $serializerFactory = new SerializerFactory();
    $adapterFactory = new AdapterFactory($serializerFactory);

    try {
        // Try Redis first
        $redisConfig = $config->redis->toArray();
        $options = [
            'defaultSerializer' => 'Json',
            'lifetime'          => $config->cache->lifetime,
            'prefix'            => $config->cache->prefix,
            'host'              => $redisConfig['host'],
            'port'              => $redisConfig['port'],
            'index'             => $redisConfig['index'],
        ];

        return $adapterFactory->newInstance('redis', $options);
    } catch (\Exception $e) {
        // Fallback to file cache
        $options = [
            'defaultSerializer' => 'Json',
            'lifetime'          => $config->cache->lifetime,
            'prefix'            => $config->cache->prefix,
            'storageDir'        => $config->application->cacheDir,
        ];

        return $adapterFactory->newInstance('stream', $options);
    }
});

/**
 * Logger service using Monolog
 */
$di->setShared('logger', function () {
    $config = $this->getConfig();
    $logFile = $config->application->logsDir . 'app-' . date('Y-m-d') . '.log';

    $logger = new MonologLogger('tahmin1x2');
    $logger->pushHandler(new StreamHandler($logFile, MonologLogger::INFO));

    return $logger;
});

/**
 * Response service
 */
$di->setShared('response', function () {
    $response = new \Phalcon\Http\Response();
    $response->setContentType('application/json', 'UTF-8');
    return $response;
});

/**
 * Request service
 */
$di->setShared('request', function () {
    return new \Phalcon\Http\Request();
});

/**
 * View service
 */
$di->setShared('view', function () {
    $config = $this->getConfig();

    $view = new \Phalcon\Mvc\View();
    $view->setViewsDir($config->application->viewsDir);

    // Register Volt as template engine
    $view->registerEngines([
        '.phtml' => \Phalcon\Mvc\View\Engine\Php::class
    ]);

    return $view;
});

/**
 * Tag service for HTML helpers
 */
$di->setShared('tag', function () {
    return new \Phalcon\Html\TagFactory();
});

/**
 * Flash Session service
 */
$di->setShared('flashSession', function () {
    return new \Phalcon\Flash\Session();
});

/**
 * Session service
 */
$di->setShared('session', function () {
    $session = new \Phalcon\Session\Manager();
    $files = new \Phalcon\Session\Adapter\Stream([
        'savePath' => sys_get_temp_dir(),
    ]);
    $session->setAdapter($files);
    $session->start();

    return $session;
});

/**
 * Escaper service
 */
$di->setShared('escaper', function () {
    return new \Phalcon\Html\Escaper();
});

/**
 * Router service
 */
$di->setShared('router', function () {
    $router = new \Phalcon\Mvc\Router(false);
    $router->removeExtraSlashes(true);

    // Load routes
    require APP_PATH . '/config/routes.php';

    return $router;
});

/**
 * Security service
 */
$di->setShared('security', function () {
    $security = new \Phalcon\Encryption\Security();
    $config = $this->getConfig();

    $security->setWorkFactor($config->security->workFactor);

    return $security;
});

/**
 * JWT Service
 */
$di->setShared('jwt', function () {
    return new \Tahmin\Services\JwtService();
});

/**
 * S3/MinIO Service
 */
$di->setShared('s3', function () {
    $config = $this->getConfig();
    $s3Config = $config->s3->toArray();

    return new \Aws\S3\S3Client([
        'version' => $s3Config['version'],
        'region'  => $s3Config['region'],
        'endpoint' => $s3Config['endpoint'],
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $s3Config['key'],
            'secret' => $s3Config['secret'],
        ],
    ]);
});

return $di;
