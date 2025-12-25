<?php

namespace App\Routing;

class ApiRouter
{
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '/api')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Register a GET route
     */
    public function get(string $path, callable $handler): void
    {
        $this->registerRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, callable $handler): void
    {
        $this->registerRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, callable $handler): void
    {
        $this->registerRoute('PUT', $path, $handler);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, callable $handler): void
    {
        $this->registerRoute('DELETE', $path, $handler);
    }

    /**
     * Register resource routes (standard RESTful CRUD)
     * Generates: GET /{resource}, GET /{resource}/{id}, POST /{resource}, PUT /{resource}/{id}, DELETE /{resource}/{id}
     */
    public function resource(string $name, string $controllerClass): void
    {
        $this->get("/{$name}", [$controllerClass, 'index']);
        $this->get("/{$name}/{id}", [$controllerClass, 'show']);
        $this->post("/{$name}", [$controllerClass, 'store']);
        $this->put("/{$name}/{id}", [$controllerClass, 'update']);
        $this->delete("/{$name}/{id}", [$controllerClass, 'destroy']);
    }

    /**
     * Match and dispatch request
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $fullPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Primary: remove base path
        $path = $fullPath;
        if (strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }
        // Also support stripping a plain '/api' base if frontend calls absolute '/api/...'
        $plainApi = '/api';
        if (strpos($path, $plainApi) === 0) {
            $path = substr($path, strlen($plainApi));
        }
        $path = '/' . ltrim($path, '/');

        // Find matching route (with base removed)
        $match = $this->matchRoute($method, $path);

        // Fallback: try matching against full path without base removal
        if (!$match) {
            $fallbackPath = '/' . ltrim($fullPath, '/');
            $match = $this->matchRoute($method, $fallbackPath);
        }

        if (!$match) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Route not found']);
            return;
        }

        // Call handler
        call_user_func_array($match['handler'], $match['params']);
    }

    /**
     * Match route pattern against request path
     */
    private function matchRoute(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $route['path'];
            $params = [];

            // Convert {id} to regex pattern and extract values
            $pattern = preg_replace_callback('/{(\w+)}/', function($matches) {
                return '(?P<' . $matches[1] . '>\d+)';
            }, $pattern);

            // Check if path matches
            if (preg_match('#^' . $pattern . '$#', $path, $pathParams)) {
                // Extract named params only
                foreach ($pathParams as $key => $value) {
                    if (!is_numeric($key)) {
                        $params[$key] = (int)$value;
                    }
                }

                return [
                    'handler' => $route['handler'],
                    'params' => array_values($params)
                ];
            }
        }

        return null;
    }

    /**
     * Register a route
     */
    private function registerRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
}
