<?php

namespace Core;

class Router {
    protected array $routes = [];
    public Request $request;
    public Response $response;

    public function __construct(Request $request, Response $response) {
        $this->request  = $request;
        $this->response = $response;
    }

    public function get(string $path, $callback): void {
        $this->routes['get'][$path] = $callback;
    }

    public function post(string $path, $callback): void {
        $this->routes['post'][$path] = $callback;
    }

    public function resolve() {
        $path   = $this->request->getPath();
        $method = $this->request->getMethod();

        // Strip the /php-app prefix when running under a sub-directory
        $basePath = rtrim(str_replace('/public', '', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($basePath !== '' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }

        $callback = $this->routes[$method][$path] ?? false;

        if ($callback === false) {
            $this->response->setStatusCode(404);
            return $this->render404();
        }

        if (is_string($callback)) {
            return $this->renderView($callback);
        }

        if (is_array($callback)) {
            /** @var Controller $controller */
            $controller  = new $callback[0]();
            $callback[0] = $controller;

            foreach ($controller->getMiddlewares() as $middleware) {
                $middleware->execute();
            }
        }

        return call_user_func($callback, $this->request, $this->response);
    }

    public function renderView(string $view, array $params = []): string {
        $layoutContent = $this->layoutContent($params);
        $viewContent   = $this->renderOnlyView($view, $params);
        return str_replace('{{content}}', $viewContent, $layoutContent);
    }

    protected function layoutContent(array $params = []): string {
        foreach ($params as $key => $value) {
            $$key = $value;
        }
        ob_start();
        include __DIR__ . '/../app/Views/layout/main.php';
        return ob_get_clean();
    }

    public function renderOnlyView(string $view, array $params = []): string {
        foreach ($params as $key => $value) {
            $$key = $value;
        }
        ob_start();
        $viewFile = __DIR__ . "/../app/Views/{$view}.php";
        if (!file_exists($viewFile)) {
            return "<p>View not found: {$view}</p>";
        }
        include $viewFile;
        return ob_get_clean();
    }

    private function render404(): string {
        $layout  = $this->layoutContent(['title' => '404 Not Found']);
        $content = '<div style="text-align:center;padding:4rem;">
            <h1 style="font-size:4rem;font-weight:700;color:#22c55e;">404</h1>
            <p style="font-size:1.25rem;margin-bottom:1rem;">Page not found.</p>
            <a href="/php-app/" style="color:#22c55e;">Return to Dashboard</a>
        </div>';
        return str_replace('{{content}}', $content, $layout);
    }
}
