<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Application;
use App\Models\User;

class AuthController extends Controller {
    protected User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function login(Request $request, Response $response) {
        if ($request->getMethod() === 'post') {
            $data = $request->getBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            $user = $this->userModel->findByEmail($email);

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_active']) {
                    Application::$app->session->set('user', $user);
                    
                    // Update last login
                    $this->userModel->update($user['uid'], [
                        'last_login' => date('Y-m-d H:i:s')
                    ]);
                    
                    return $response->redirect('/php-app/');
                } else {
                    Application::$app->session->setFlash('error', 'Your account is inactive.');
                }
            } else {
                Application::$app->session->setFlash('error', 'Invalid email or password.');
            }
            
            return $this->render('auth/login', [
                'title' => 'Login',
                'email' => $email
            ]);
        }

        return $this->render('auth/login', [
            'title' => 'Login'
        ]);
    }

    public function logout(Request $request, Response $response) {
        Application::$app->session->remove('user');
        Application::$app->session->destroy();
        return $response->redirect('/php-app/login');
    }
}
