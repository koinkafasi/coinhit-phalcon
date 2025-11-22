<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application;

error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

try {
    // Register an autoloader
    require_once BASE_PATH . '/vendor/autoload.php';

    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();

    // Create a DI
    $di = new FactoryDefault();

    // Include services
    include APP_PATH . '/config/services.php';

    // Handle the request
    $application = new Application($di);

    // Register event manager
    $eventsManager = new \Phalcon\Events\Manager();

    // Attach Auth middleware
    $eventsManager->attach(
        'dispatch:beforeExecuteRoute',
        new \Tahmin\Middleware\AuthMiddleware()
    );

    // Attach CORS middleware
    $eventsManager->attach(
        'application:beforeSendResponse',
        new \Tahmin\Middleware\CorsMiddleware()
    );

    $application->setEventsManager($eventsManager);

    // Send response
    echo $application->handle($_SERVER['REQUEST_URI'])->getContent();
} catch (\Exception $e) {
    // Handle errors
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'error' => ($_ENV['APP_DEBUG'] ?? false) ? $e->getMessage() : 'An error occurred'
    ]);
}
