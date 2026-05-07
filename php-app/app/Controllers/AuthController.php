<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Application;
use Core\Auth;
use Core\CSRF;
use Core\Validator;
use App\Models\User;

class AuthController extends Controller {

    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function login(Request $request, Response $response) {
        // Already logged in
        if (Auth::check()) {
            return $response->redirect('/php-app/');
        }

        if ($request->getMethod() === 'post') {
            $this->validateCsrf();

            $data     = $request->getBody();
            $email    = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';

            $v = new Validator(['email' => $email, 'password' => $password]);
            $v->required('email')->email('email')->required('password');

            if ($v->fails()) {
                Application::$app->session->setFlash('error', $v->errorString());
                return $this->render('auth/login', ['title' => 'Login', 'email' => $email]);
            }

            $user = $this->userModel->findByEmail($email);

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    Application::$app->session->setFlash('error', 'Your account has been deactivated. Contact your administrator.');
                    return $this->render('auth/login', ['title' => 'Login', 'email' => $email]);
                }

                // Log in
                Auth::login($user);

                // Update last login timestamp
                $this->userModel->update($user['uid'], ['last_login' => date('Y-m-d H:i:s')]);

                CSRF::regenerate();
                return $response->redirect('/php-app/');
            }

            Application::$app->session->setFlash('error', 'Invalid email or password.');
            return $this->render('auth/login', ['title' => 'Login', 'email' => $email]);
        }

        return $this->render('auth/login', ['title' => 'Login']);
    }

    public function register(Request $request, Response $response) {
        // Check if registration is allowed
        $db      = \Core\Database::getInstance();
        $setting = $db->fetch(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'allow_registration'",
            []
        );
        if (!$setting || $setting['setting_value'] !== 'true') {
            Application::$app->session->setFlash('error', 'Public registration is disabled. Contact your administrator.');
            return $response->redirect('/php-app/login');
        }

        if (Auth::check()) {
            return $response->redirect('/php-app/');
        }

        if ($request->getMethod() === 'post') {
            $this->validateCsrf();

            $data = $request->getBody();
            $v    = new Validator($data);
            $v->required('name')
              ->required('email')->email('email')
              ->required('password')->minLength('password', 8)
              ->required('password_confirm')->matches('password_confirm', 'password', 'Password confirmation');

            if ($v->fails()) {
                Application::$app->session->setFlash('error', $v->errorString());
                return $this->render('auth/register', ['title' => 'Register', 'data' => $data]);
            }

            if ($this->userModel->emailExists($data['email'])) {
                Application::$app->session->setFlash('error', 'An account with that email already exists.');
                return $this->render('auth/register', ['title' => 'Register', 'data' => $data]);
            }

            $uid = 'u_' . bin2hex(random_bytes(8));
            $this->userModel->create([
                'uid'           => $uid,
                'email'         => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'name'          => $data['name'],
                'role'          => 'user',
                'is_active'     => 1,
            ]);

            Application::$app->session->setFlash('success', 'Account created. You can now log in.');
            CSRF::regenerate();
            return $response->redirect('/php-app/login');
        }

        return $this->render('auth/register', ['title' => 'Register']);
    }

    public function logout(Request $request, Response $response) {
        Auth::logout();
        return $response->redirect('/php-app/login');
    }
}
