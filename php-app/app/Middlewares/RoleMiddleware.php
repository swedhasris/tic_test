<?php

namespace App\Middlewares;

use Core\Middleware;
use Core\Application;
use Core\Auth;

/**
 * Restrict access to users with one of the specified roles.
 * Usage: new RoleMiddleware(['admin', 'super_admin'])
 */
class RoleMiddleware extends Middleware {

    private array $allowedRoles;

    public function __construct(array $allowedRoles) {
        $this->allowedRoles = $allowedRoles;
    }

    public function execute(): void {
        $user = Auth::user();

        if (!$user) {
            Application::$app->response->redirect('/php-app/login');
            return;
        }

        if (!in_array($user['role'], $this->allowedRoles, true)) {
            Application::$app->response->setStatusCode(403);
            Application::$app->session->setFlash('error', 'You do not have permission to access that page.');
            Application::$app->response->redirect('/php-app/');
        }
    }
}
