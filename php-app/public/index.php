<?php

use Core\Application;

require_once __DIR__ . '/../core/Application.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';

// Simple autoloader for Controllers and Models
spl_autoload_register(function ($class) {
    $root = dirname(__DIR__);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    
    // Core is already loaded, but just in case
    if (strpos($class, 'Core') === 0) {
        $file = $root . DIRECTORY_SEPARATOR . $class . '.php';
    } else {
        // Map App\ to app/
        $class = str_replace('App', 'app', $class);
        $file = $root . DIRECTORY_SEPARATOR . $class . '.php';
    }
    
    if (file_exists($file)) {
        require_once $file;
    }
});

$app = new Application(dirname(__DIR__));

// Define Routes
$app->router->get('/', [App\Controllers\DashboardController::class, 'index']);
$app->router->get('/login', [App\Controllers\AuthController::class, 'login']);
$app->router->post('/login', [App\Controllers\AuthController::class, 'login']);
$app->router->get('/tickets', [App\Controllers\TicketController::class, 'index']);
$app->router->get('/tickets/create', [App\Controllers\TicketController::class, 'create']);
$app->router->post('/tickets/create', [App\Controllers\TicketController::class, 'create']);
$app->router->get('/tickets/detail', [App\Controllers\TicketController::class, 'detail']);
$app->router->post('/tickets/comment', [App\Controllers\TicketController::class, 'storeComment']);

$app->router->get('/users', [App\Controllers\UserController::class, 'index']);
$app->router->get('/users/create', [App\Controllers\UserController::class, 'create']);
$app->router->post('/users/create', [App\Controllers\UserController::class, 'create']);

$app->run();
