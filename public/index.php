<?php
declare(strict_types=1);

use App\Auth;
use App\Config;
use App\Helpers;
use App\Router;

require __DIR__ . '/../src/Autoload.php';

Config::load(__DIR__ . '/../config.php');
date_default_timezone_set((string)Config::get('timezone', 'UTC'));

if ((string)Config::get('app_env', 'production') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

Auth::startSession();

$router = new Router();

// Public
$router->get('/login',  [\App\Controllers\AuthController::class, 'showLogin'], null);
$router->post('/login', [\App\Controllers\AuthController::class, 'doLogin'],   null);
$router->get('/logout', [\App\Controllers\AuthController::class, 'logout'],    'view');

// Dashboard
$router->get('/',          [\App\Controllers\DashboardController::class, 'index']);
$router->get('/dashboard', [\App\Controllers\DashboardController::class, 'index']);

// Customers
$router->get('/customers',            [\App\Controllers\CustomerController::class, 'index']);
$router->get('/customers/new',        [\App\Controllers\CustomerController::class, 'create'], 'write');
$router->post('/customers',           [\App\Controllers\CustomerController::class, 'store'],  'write');
$router->get('/customers/{id}/edit',  [\App\Controllers\CustomerController::class, 'edit'],   'write');
$router->post('/customers/{id}',      [\App\Controllers\CustomerController::class, 'update'], 'write');
$router->post('/customers/{id}/delete',[\App\Controllers\CustomerController::class, 'destroy'],'admin');

// Raw materials
$router->get('/raw-materials',             [\App\Controllers\RawMaterialController::class, 'index']);
$router->get('/raw-materials/new',         [\App\Controllers\RawMaterialController::class, 'create'], 'write');
$router->post('/raw-materials',            [\App\Controllers\RawMaterialController::class, 'store'],  'write');
$router->get('/raw-materials/{id}/edit',   [\App\Controllers\RawMaterialController::class, 'edit'],   'write');
$router->post('/raw-materials/{id}',       [\App\Controllers\RawMaterialController::class, 'update'], 'write');
$router->post('/raw-materials/{id}/delete',[\App\Controllers\RawMaterialController::class, 'destroy'],'admin');
$router->post('/raw-materials/{id}/adjust',[\App\Controllers\RawMaterialController::class, 'adjust'], 'write');

// Products
$router->get('/products',             [\App\Controllers\ProductController::class, 'index']);
$router->get('/products/new',         [\App\Controllers\ProductController::class, 'create'], 'write');
$router->post('/products',            [\App\Controllers\ProductController::class, 'store'],  'write');
$router->get('/products/{id}/edit',   [\App\Controllers\ProductController::class, 'edit'],   'write');
$router->post('/products/{id}',       [\App\Controllers\ProductController::class, 'update'], 'write');
$router->post('/products/{id}/delete',[\App\Controllers\ProductController::class, 'destroy'],'admin');

// BOM (nested under product)
$router->get('/products/{id}/bom',       [\App\Controllers\BomController::class, 'index']);
$router->post('/products/{id}/bom',      [\App\Controllers\BomController::class, 'store'],   'write');
$router->post('/products/{id}/bom/{bid}/delete', [\App\Controllers\BomController::class, 'destroy'], 'write');

// Price list
$router->get('/price-list',          [\App\Controllers\PriceListController::class, 'index']);
$router->post('/price-list',         [\App\Controllers\PriceListController::class, 'upsert'], 'write');
$router->post('/price-list/{id}/delete', [\App\Controllers\PriceListController::class, 'destroy'], 'write');

// Work orders
$router->get('/work-orders',                [\App\Controllers\WorkOrderController::class, 'index']);
$router->get('/work-orders/new',            [\App\Controllers\WorkOrderController::class, 'create'], 'write');
$router->post('/work-orders',               [\App\Controllers\WorkOrderController::class, 'store'],  'write');
$router->get('/work-orders/{id}',           [\App\Controllers\WorkOrderController::class, 'show']);
$router->post('/work-orders/{id}/status',   [\App\Controllers\WorkOrderController::class, 'changeStatus'], 'write');
$router->post('/work-orders/{id}/delete',   [\App\Controllers\WorkOrderController::class, 'destroy'], 'admin');
// AJAX helper for unit-price lookup
$router->get('/api/price',                  [\App\Controllers\WorkOrderController::class, 'priceLookup']);

// Receipts
$router->get('/receipts',              [\App\Controllers\ReceiptController::class, 'index']);
$router->get('/receipts/new',          [\App\Controllers\ReceiptController::class, 'create'], 'write');
$router->post('/receipts',             [\App\Controllers\ReceiptController::class, 'store'],  'write');
$router->post('/receipts/{id}/delete', [\App\Controllers\ReceiptController::class, 'destroy'], 'admin');

// Reports
$router->get('/reports/rm-stock',           [\App\Controllers\ReportController::class, 'rmStock']);
$router->get('/reports/fg-stock',           [\App\Controllers\ReportController::class, 'fgStock']);
$router->get('/reports/wo-status',          [\App\Controllers\ReportController::class, 'woStatus']);
$router->get('/reports/customer-outstanding',[\App\Controllers\ReportController::class, 'customerOutstanding']);

// Users (admin)
$router->get('/users',                [\App\Controllers\UserController::class, 'index'],  'admin');
$router->get('/users/new',            [\App\Controllers\UserController::class, 'create'], 'admin');
$router->post('/users',               [\App\Controllers\UserController::class, 'store'],  'admin');
$router->get('/users/{id}/edit',      [\App\Controllers\UserController::class, 'edit'],   'admin');
$router->post('/users/{id}',          [\App\Controllers\UserController::class, 'update'], 'admin');
$router->post('/users/{id}/delete',   [\App\Controllers\UserController::class, 'destroy'],'admin');

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (\Throwable $e) {
    if ((string)Config::get('app_env') === 'development') {
        http_response_code(500);
        echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        error_log((string)$e);
        Helpers::abort(500, 'An error occurred. Please try again later.');
    }
}
