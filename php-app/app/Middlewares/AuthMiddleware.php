<?php

namespace App\Middlewares;

use Core\Middleware;
use Core\Application;
use Core\Auth;

class AuthMiddleware extends Middleware {
    public function execute(): void {
        if (!Auth::check()) {
            Application::$app->response->redirect('/php-app/login');
        }
    }
}
