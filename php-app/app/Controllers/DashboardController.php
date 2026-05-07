<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use App\Middlewares\AuthMiddleware;

class DashboardController extends Controller {
    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
    }

    public function index(Request $request, Response $response) {
        return $this->render('dashboard/index', [
            'title' => 'Dashboard'
        ]);
    }
}
