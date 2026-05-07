<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Application;
use App\Models\User;
use App\Middlewares\AuthMiddleware;

class UserController extends Controller {
    protected User $userModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->userModel = new User();
    }

    public function index(Request $request, Response $response) {
        // Check if current user is admin
        $currentUser = Application::$app->session->get('user');
        if (!in_array($currentUser['role'], ['admin', 'super_admin', 'ultra_super_admin'])) {
            return $response->redirect('/php-app/');
        }

        $users = $this->userModel->getAll();

        return $this->render('users/index', [
            'title' => 'User Management',
            'users' => $users
        ]);
    }

    public function create(Request $request, Response $response) {
        if ($request->getMethod() === 'post') {
            $data = $request->getBody();
            // Simple validation
            if (empty($data['email']) || empty($data['password'])) {
                Application::$app->session->setFlash('error', 'Email and password are required.');
                return $response->redirect('/php-app/users/create');
            }

            $data['uid'] = 'u_' . bin2hex(random_bytes(8));
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);

            $this->userModel->create($data);
            return $response->redirect('/php-app/users');
        }

        return $this->render('users/create', [
            'title' => 'Create New User'
        ]);
    }
}
