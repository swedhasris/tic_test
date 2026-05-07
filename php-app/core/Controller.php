<?php

namespace Core;

class Controller {
    protected array $middlewares = [];

    public function registerMiddleware(Middleware $middleware) {
        $this->middlewares[] = $middleware;
    }

    public function getMiddlewares(): array {
        return $this->middlewares;
    }

    public function render($view, $params = []) {
        return Application::$app->router->renderView($view, $params);
    }
}
