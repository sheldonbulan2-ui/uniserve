<?php
declare(strict_types=1);

/**
 * UniServe RESTful API Router
 * Supports HTTP methods, URL parameters (e.g., /catalog/{id}), and middleware pipelines.
 */
class Router {
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): void {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function get(string $path, callable|array $handler, array $middlewares = []): void {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): void {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): void {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): void {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    public function dispatch(string $method, string $uri): void {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $method = strtoupper($method);

        // Strip prefix if present (e.g., /api/v1)
        if (str_starts_with($uri, '/api/v1')) {
            $uri = substr($uri, 7);
        }
        if ($uri === '' || $uri === false) {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                // Filter string keys from named captures
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run route-level middlewares
                foreach ($route['middlewares'] as $mw) {
                    if (is_callable($mw)) {
                        $mw();
                    }
                }

                // Call handler
                if (is_callable($route['handler'])) {
                    call_user_func($route['handler'], $params);
                    return;
                }

                if (is_array($route['handler']) && count($route['handler']) === 2) {
                    [$class, $action] = $route['handler'];
                    $instance = new $class();
                    $instance->$action($params);
                    return;
                }
            }
        }

        Response::notFound("Endpoint not found for {$method} {$uri}");
    }
}
