<?php
declare(strict_types=1);

namespace App;

final class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:callable,role:?string}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, ?string $role = 'view'): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'role'    => $role,
        ];
    }

    public function get(string $p, callable $h, ?string $role = 'view'): void  { $this->add('GET', $p, $h, $role); }
    public function post(string $p, callable $h, ?string $role = 'write'): void { $this->add('POST', $p, $h, $role); }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        // strip a possible subdirectory script base
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }
        if ($path === '' || $path === false) $path = '/';

        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $r['pattern']) . '$#';
            if (preg_match($regex, $path, $m)) {
                if ($r['role'] !== null) {
                    if (!Auth::check()) {
                        Helpers::redirect('/login');
                    }
                    if (!Auth::can($r['role'])) {
                        Helpers::abort(403, 'You do not have permission to perform this action.');
                    }
                }
                if ($method === 'POST' && !Csrf::verify($_POST['_csrf'] ?? null)) {
                    Helpers::abort(400, 'Invalid CSRF token.');
                }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                ($r['handler'])($params);
                return;
            }
        }
        Helpers::abort(404, 'Page not found.');
    }
}
