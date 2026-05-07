<?php

namespace Core;

class Request {
    public function getPath(): string {
        $path     = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        return $position === false ? $path : substr($path, 0, $position);
    }

    public function getMethod(): string {
        return strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
    }

    public function getBody(): array {
        $body = [];

        if ($this->getMethod() === 'get') {
            foreach ($_GET as $key => $value) {
                $body[$key] = is_array($value)
                    ? array_map(fn($v) => htmlspecialchars(strip_tags((string)$v), ENT_QUOTES, 'UTF-8'), $value)
                    : htmlspecialchars(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
            }
        }

        if ($this->getMethod() === 'post') {
            foreach ($_POST as $key => $value) {
                // Don't sanitize textarea/description fields — let controllers handle it
                $body[$key] = is_array($value)
                    ? array_map(fn($v) => (string)$v, $value)
                    : (string)$value;
            }
        }

        // Handle JSON body (for API requests)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            if ($input) {
                $json = json_decode($input, true);
                if (is_array($json)) {
                    foreach ($json as $key => $value) {
                        $body[$key] = $value;
                    }
                }
            }
        }

        return $body;
    }

    public function isAjax(): bool {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    public function getHeader(string $name): string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? '';
    }

    public function ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
