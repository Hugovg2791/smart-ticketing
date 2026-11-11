<?php
namespace App\Core;

class Router {
    protected $routes = [];

    public function get($uri, $controllerAction) {
        $this->routes['GET'][$uri] = $controllerAction;
    }

    public function post($uri, $controllerAction) {
        $this->routes['POST'][$uri] = $controllerAction;
    }

    public function dispatch($uri, $method) {
        // Remove query string from URI
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Protect Dashboard routes
        if (strpos($uri, '/tickets') === 0 && !isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        // Protect Settings routes
        if (strpos($uri, '/settings') === 0 && !isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        // Protect Ingest routes
        if (strpos($uri, '/ingest') === 0 && !isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        // 1. Check exact match first (backward compatible)
        if (array_key_exists($uri, $this->routes[$method] ?? [])) {
            $action = $this->routes[$method][$uri];
            list($controller, $methodName) = explode('@', $action);
            $controllerClass = "App\\Controllers\\" . $controller;
            
            if (class_exists($controllerClass)) {
                $controllerInstance = new $controllerClass();
                if (method_exists($controllerInstance, $methodName)) {
                    $controllerInstance->$methodName();
                    return;
                }
            }
        }

        // 2. Check dynamic routes with {param} placeholders
        foreach ($this->routes[$method] ?? [] as $route => $action) {
            if (strpos($route, '{') === false) {
                continue; // Skip exact routes (already checked)
            }

            // Convert /tickets/{id} to regex: /tickets/(?P<id>[^/]+)
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                list($controller, $methodName) = explode('@', $action);
                $controllerClass = "App\\Controllers\\" . $controller;

                if (class_exists($controllerClass)) {
                    $controllerInstance = new $controllerClass();
                    if (method_exists($controllerInstance, $methodName)) {
                        // Extract only named captures (not numeric keys)
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        $controllerInstance->$methodName(...array_values($params));
                        return;
                    }
                }
            }
        }

        http_response_code(404);
        require __DIR__ . '/../Views/errors/404.php';
    }
}

