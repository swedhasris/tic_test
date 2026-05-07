<?php

namespace Core;

class Router {
    protected array $routes = [];
    public Request $request;
    public Response $response;

    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }

    public function get($path, $callback) {
        $this->routes['get'][$path] = $callback;
    }

    public function post($path, $callback) {
        $this->routes['post'][$path] = $callback;
    }

    public function resolve() {
        $path = $this->request->getPath();
        $method = $this->request->getMethod();
        
        // Remove base path if it exists
        $basePath = str_replace('/public', '', dirname($_SERVER['PHP_SELF']));
        if ($basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        if ($path === '') $path = '/';

        $callback = $this->routes[$method][$path] ?? false;

        if ($callback === false) {
            $this->response->setStatusCode(404);
            echo "Not Found";
            return;
        }

        if (is_string($callback)) {
            // Handle view rendering
            return $this->renderView($callback);
        }

        if (is_array($callback)) {
            // Handle Controller method [ControllerClass, 'method']
            $controller = new $callback[0]();
            $callback[0] = $controller;
            
            foreach ($controller->getMiddlewares() as $middleware) {
                $middleware->execute();
            }
        }

        return call_user_func($callback, $this->request, $this->response);
    }

    public function renderView($view, $params = []) {
        $layoutContent = $this->layoutContent();
        $viewContent = $this->renderOnlyView($view, $params);
        return str_replace('{{content}}', $viewContent, $layoutContent);
    }

    protected function layoutContent() {
        ob_start();
        include_once __DIR__ . "/../app/Views/layout/main.php";
        return ob_get_clean();
    }

    protected function renderOnlyView($view, $params) {
        foreach ($params as $key => $value) {
            $$key = $value;
        }
        ob_start();
        include_once __DIR__ . "/../app/Views/$view.php";
        return ob_get_clean();
    }
}
