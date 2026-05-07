<?php

namespace App\Middlewares;

use Core\Middleware;
use Core\Application;

class AuthMiddleware extends Middleware {
    public function execute() {
        if (!Application::$app->session->get('user')) {
            Application::$app->response->redirect('/php-app/login');
        }
    }
}
