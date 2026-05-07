<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Application;
use Core\Auth;
use Core\CSRF;
use Core\Validator;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RoleMiddleware;
use App\Models\User;

class UserController extends Controller {

    private User $userModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->registerMiddleware(new RoleMiddleware(['admin', 'super_admin', 'ultra_super_admin']));
        $this->userModel = new User();
    }

    public function index(Request $request, Response $response) {
        $filters = $request->getBody();
        $users   = $this->userModel->getAll($filters);

        return $this->render('users/index', [
            'title'   => 'User Management',
            'users'   => $users,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, Response $response) {
        if ($request->getMethod() === 'post') {
            $this->validateCsrf();

            $data = $request->getBody();
            $v    = new Validator($data);
            $v->required('name')
              ->required('email')->email('email')
              ->required('password')->minLength('password', 8)
              ->required('role');

            if ($v->fails()) {
                Application::$app->session->setFlash('error', $v->errorString());
                return $this->render('users/create', ['title' => 'Create User', 'data' => $data]);
            }

            if ($this->userModel->emailExists($data['email'])) {
                Application::$app->session->setFlash('error', 'A user with that email already exists.');
                return $this->render('users/create', ['title' => 'Create User', 'data' => $data]);
            }

            $uid = 'u_' . bin2hex(random_bytes(8));
            $this->userModel->create([
                'uid'           => $uid,
                'email'         => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'name'          => $data['name'],
                'role'          => $data['role'],
                'department'    => $data['department'] ?? '',
                'title'         => $data['title'] ?? '',
                'phone'         => $data['phone'] ?? '',
                'is_active'     => 1,
            ]);

            CSRF::regenerate();
            Application::$app->session->setFlash('success', 'User created successfully.');
            return $response->redirect('/php-app/users');
        }

        return $this->render('users/create', ['title' => 'Create User', 'data' => []]);
    }

    public function edit(Request $request, Response $response) {
        $params = $request->getBody();
        $uid    = $params['uid'] ?? '';

        if (!$uid) {
            return $response->redirect('/php-app/users');
        }

        $editUser = $this->userModel->findByUid($uid);
        if (!$editUser) {
            Application::$app->session->setFlash('error', 'User not found.');
            return $response->redirect('/php-app/users');
        }

        if ($request->getMethod() === 'post') {
            $this->validateCsrf();

            $data = $request->getBody();
            $v    = new Validator($data);
            $v->required('name')->required('email')->email('email')->required('role');

            if ($v->fails()) {
                Application::$app->session->setFlash('error', $v->errorString());
                return $this->render('users/edit', ['title' => 'Edit User', 'editUser' => array_merge($editUser, $data)]);
            }

            if ($this->userModel->emailExists($data['email'], $uid)) {
                Application::$app->session->setFlash('error', 'That email is already in use by another account.');
                return $this->render('users/edit', ['title' => 'Edit User', 'editUser' => array_merge($editUser, $data)]);
            }

            $updateData = [
                'name'       => $data['name'],
                'email'      => $data['email'],
                'role'       => $data['role'],
                'department' => $data['department'] ?? '',
                'title'      => $data['title'] ?? '',
                'phone'      => $data['phone'] ?? '',
            ];

            // Only update password if provided
            if (!empty($data['password'])) {
                if (strlen($data['password']) < 8) {
                    Application::$app->session->setFlash('error', 'Password must be at least 8 characters.');
                    return $this->render('users/edit', ['title' => 'Edit User', 'editUser' => array_merge($editUser, $data)]);
                }
                $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $this->userModel->update($uid, $updateData);

            CSRF::regenerate();
            Application::$app->session->setFlash('success', 'User updated successfully.');
            return $response->redirect('/php-app/users');
        }

        return $this->render('users/edit', ['title' => 'Edit User', 'editUser' => $editUser]);
    }

    public function delete(Request $request, Response $response) {
        $this->validateCsrf();

        $currentUser = $this->currentUser();
        $data        = $request->getBody();
        $uid         = $data['uid'] ?? '';

        if (!$uid) {
            return $response->redirect('/php-app/users');
        }

        // Prevent self-deletion
        if ($uid === $currentUser['uid']) {
            Application::$app->session->setFlash('error', 'You cannot delete your own account.');
            return $response->redirect('/php-app/users');
        }

        $this->userModel->deleteByUid($uid);
        Application::$app->session->setFlash('success', 'User deleted.');
        return $response->redirect('/php-app/users');
    }

    public function toggleActive(Request $request, Response $response) {
        $this->validateCsrf();

        $data = $request->getBody();
        $uid  = $data['uid'] ?? '';

        if (!$uid) {
            return $response->redirect('/php-app/users');
        }

        $editUser = $this->userModel->findByUid($uid);
        if ($editUser) {
            $this->userModel->update($uid, ['is_active' => $editUser['is_active'] ? 0 : 1]);
            $status = $editUser['is_active'] ? 'deactivated' : 'activated';
            Application::$app->session->setFlash('success', "User {$status} successfully.");
        }

        return $response->redirect('/php-app/users');
    }
}
