<?php

declare(strict_types=1);

// ── Autoload core files ───────────────────────────────────────────────────────
$root = dirname(__DIR__);

require_once $root . '/core/Application.php';
require_once $root . '/core/Router.php';
require_once $root . '/core/Request.php';
require_once $root . '/core/Response.php';
require_once $root . '/core/Controller.php';
require_once $root . '/core/Model.php';
require_once $root . '/core/Database.php';
require_once $root . '/core/Session.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/CSRF.php';
require_once $root . '/core/Validator.php';
require_once $root . '/core/Middleware.php';

// ── PSR-4 style autoloader ────────────────────────────────────────────────────
spl_autoload_register(function (string $class) use ($root): void {
    // Core\ → core/
    if (strpos($class, 'Core\\') === 0) {
        $rel  = str_replace('Core\\', 'core/', $class);
        $file = $root . '/' . str_replace('\\', '/', $rel) . '.php';
    }
    // App\ → app/
    elseif (strpos($class, 'App\\') === 0) {
        $rel  = str_replace('App\\', 'app/', $class);
        $file = $root . '/' . str_replace('\\', '/', $rel) . '.php';
    } else {
        return;
    }

    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Bootstrap application ─────────────────────────────────────────────────────
$app = new \Core\Application($root);

// ── Register all routes ───────────────────────────────────────────────────────
require_once $root . '/config/routes.php';

// ── Run ───────────────────────────────────────────────────────────────────────
echo $app->run();
