<?php

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

// Read route declarations without booting the API, connecting to its database,
// or requiring its vendor directory. Usage: php tools/export-api-routes.php /path/to/api
require dirname(__DIR__).'/vendor/autoload.php';

$root = $argv[1] ?? '';
if (! is_file("$root/routes/api.php")) {
    fwrite(STDERR, "Provide the API project directory.\n");
    exit(1);
}

$container = new Container;
$router = new Router(new Dispatcher($container), $container);
Route::swap($router);
ob_start();
$router->prefix('api')->group("$root/routes/api.php");
ob_end_clean();
$routes = [];
foreach ($router->getRoutes() as $route) {
    $routes[] = [
        'methods' => $route->methods(),
        'uri' => $route->uri(),
        'action' => $route->getActionName(),
    ];
}
echo json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
