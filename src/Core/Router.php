<?php

declare(strict_types=1);

namespace Unfurl\Core;

/**
 * Router - URL Routing and Request Handling
 *
 * Simple router that maps HTTP requests to controller actions.
 * Supports route parameters and RESTful routing patterns.
 */
class Router
{
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Register a GET route
     */
    public function get(string $path, callable|string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, callable|string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Add a route to the routing table
     */
    private function addRoute(string $method, string $path, callable|string $handler): void
    {
        $path = '/' . trim($path, '/');
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Dispatch the request to the appropriate handler
     *
     * @return mixed The response from the handler
     */
    public function dispatch(string $method, string $uri): mixed
    {
        // Remove base path and query string
        $uri = $this->removeBasePath($uri);
        $uri = $this->removeQueryString($uri);
        $uri = '/' . trim($uri, '/');

        // Try exact match first
        if (isset($this->routes[$method][$uri])) {
            return $this->executeHandler($this->routes[$method][$uri], []);
        }

        // Try pattern matching for routes with parameters
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $params = $this->matchRoute($route, $uri);
            if ($params !== false) {
                return $this->executeHandler($handler, $params);
            }
        }

        // No route found - 404
        http_response_code(404);
        if (file_exists(__DIR__ . '/../../public/404.php')) {
            require __DIR__ . '/../../public/404.php';
            exit;
        }
        echo '404 Not Found';
        exit;
    }

    /**
     * Match a route pattern against a URI
     *
     * @return array|false Parameters if matched, false otherwise
     */
    private function matchRoute(string $route, string $uri): array|false
    {
        // Convert route pattern to regex
        // {id} becomes named capture group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            // Extract only named parameters
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return $params;
        }

        return false;
    }

    /**
     * Execute the route handler
     *
     * @param callable|string $handler The handler to execute
     * @param array $params Route parameters
     * @return mixed Handler response
     */
    private function executeHandler(callable|string $handler, array $params): mixed
    {
        // If handler is a callable, execute it directly
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        // If handler is a string, parse it as Controller@method
        if (is_string($handler) && str_contains($handler, '@')) {
            [$controller, $method] = explode('@', $handler, 2);

            // Resolve controller class
            $controllerClass = "Unfurl\\Controllers\\{$controller}";

            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Controller not found: {$controllerClass}");
            }

            // This will be overridden by the front controller with proper DI
            throw new \RuntimeException("Controller instantiation must be handled by front controller");
        }

        throw new \RuntimeException("Invalid handler type");
    }

    /**
     * Remove base path from URI
     */
    private function removeBasePath(string $uri): string
    {
        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }
        return $uri;
    }

    /**
     * Remove query string from URI
     */
    private function removeQueryString(string $uri): string
    {
        if (str_contains($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }
        return $uri;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
