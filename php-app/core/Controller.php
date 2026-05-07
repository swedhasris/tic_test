<?php

namespace Core;

class Controller {
    protected array $middlewares = [];

    public function registerMiddleware(Middleware $middleware): void {
        $this->middlewares[] = $middleware;
    }

    public function getMiddlewares(): array {
        return $this->middlewares;
    }

    /**
     * Render a view inside the main layout.
     * $params are extracted into the view scope.
     */
    public function render(string $view, array $params = []): string {
        return Application::$app->router->renderView($view, $params);
    }

    /**
     * Render a view WITHOUT the layout (useful for partials / AJAX).
     */
    public function renderPartial(string $view, array $params = []): string {
        return Application::$app->router->renderOnlyView($view, $params);
    }

    /** Shortcut: redirect and exit. */
    protected function redirect(string $url): void {
        Application::$app->response->redirect($url);
    }

    /** Shortcut: send JSON response and exit. */
    protected function json($data, int $status = 200): void {
        Application::$app->response->setStatusCode($status);
        Application::$app->response->json($data);
    }

    /** Return the currently authenticated user array (or null). */
    protected function currentUser(): ?array {
        $user = Application::$app->session->get('user');
        return $user ?: null;
    }

    /** Validate CSRF token from POST data; abort with 403 on failure. */
    protected function validateCsrf(): void {
        $token = $_POST['_csrf_token'] ?? '';
        if (!CSRF::validate($token)) {
            http_response_code(403);
            echo 'Invalid or missing CSRF token.';
            exit;
        }
    }
}
