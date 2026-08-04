<?php
declare(strict_types=1);

use App\Auth;
use App\Config;
use App\Helpers;
use App\Router;

require __DIR__ . '/src/Autoload.php';

Config::load(__DIR__ . '/config.php');
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
$router->get('/customers/export',     [\App\Controllers\CustomerController::class, 'exportCsv'], 'admin');
$router->post('/customers/import',    [\App\Controllers\CustomerController::class, 'importCsv'], 'admin');
$router->get('/customers',            [\App\Controllers\CustomerController::class, 'index']);
$router->get('/customers/new',        [\App\Controllers\CustomerController::class, 'create'], 'write');
$router->post('/customers',           [\App\Controllers\CustomerController::class, 'store'],  'write');
$router->get('/customers/{id}/edit',  [\App\Controllers\CustomerController::class, 'edit'],   'write');
$router->post('/customers/{id}',      [\App\Controllers\CustomerController::class, 'update'], 'write');
$router->post('/customers/{id}/delete',[\App\Controllers\CustomerController::class, 'destroy'],'admin');

// Raw materials  (admin-only — masters)
$router->get('/raw-materials/export',           [\App\Controllers\RawMaterialController::class, 'exportCsv'],         'admin');
$router->post('/raw-materials/import',          [\App\Controllers\RawMaterialController::class, 'importCsv'],         'admin');
$router->post('/raw-materials/adjust-import',   [\App\Controllers\RawMaterialController::class, 'adjustImportCsv'],   'admin');
$router->get('/raw-materials',             [\App\Controllers\RawMaterialController::class, 'index'],   'admin');
$router->get('/raw-materials/new',         [\App\Controllers\RawMaterialController::class, 'create'],  'admin');
$router->post('/raw-materials',            [\App\Controllers\RawMaterialController::class, 'store'],   'admin');
$router->get('/raw-materials/{id}/edit',   [\App\Controllers\RawMaterialController::class, 'edit'],    'admin');
$router->post('/raw-materials/{id}',       [\App\Controllers\RawMaterialController::class, 'update'],  'admin');
$router->post('/raw-materials/{id}/delete',[\App\Controllers\RawMaterialController::class, 'destroy'], 'admin');
$router->post('/raw-materials/{id}/adjust',[\App\Controllers\RawMaterialController::class, 'adjust'],  'admin');

// Products (final)  (admin-only — masters)
$router->get('/products/export',      [\App\Controllers\ProductController::class, 'exportCsv'], 'admin');
$router->post('/products/import',     [\App\Controllers\ProductController::class, 'importCsv'], 'admin');
$router->get('/products',             [\App\Controllers\ProductController::class, 'index'],   'admin');
$router->get('/products/new',         [\App\Controllers\ProductController::class, 'create'],  'admin');
$router->post('/products',            [\App\Controllers\ProductController::class, 'store'],   'admin');
$router->get('/products/{id}/edit',   [\App\Controllers\ProductController::class, 'edit'],    'admin');
$router->post('/products/{id}',       [\App\Controllers\ProductController::class, 'update'],  'admin');
$router->post('/products/{id}/delete',[\App\Controllers\ProductController::class, 'destroy'], 'admin');

// BOM — part of the product master, admin-only.
$router->get('/products/{id}/bom',                 [\App\Controllers\BomController::class, 'index'],   'admin');
$router->post('/products/{id}/bom',                [\App\Controllers\BomController::class, 'store'],   'admin');
$router->post('/products/{id}/bom/{bid}/delete',   [\App\Controllers\BomController::class, 'destroy'], 'admin');
$router->get('/products/{id}/bom/export',          [\App\Controllers\BomController::class, 'exportCsv'], 'admin');
$router->post('/products/{id}/bom/import',         [\App\Controllers\BomController::class, 'importCsv'], 'admin');

// Price lists (named tiers; each customer attaches to one).
// Admin-only end-to-end: view, create, edit, delete, item management.
$router->get('/price-lists/export',                   [\App\Controllers\PriceListController::class, 'exportCsv'],   'admin');
$router->post('/price-lists/import',                  [\App\Controllers\PriceListController::class, 'importCsv'],   'admin');
$router->get('/price-lists',                          [\App\Controllers\PriceListController::class, 'index'],       'admin');
$router->get('/price-lists/new',                      [\App\Controllers\PriceListController::class, 'create'],      'admin');
$router->post('/price-lists',                         [\App\Controllers\PriceListController::class, 'store'],       'admin');
$router->get('/price-lists/{id}',                     [\App\Controllers\PriceListController::class, 'show'],        'admin');
$router->get('/price-lists/{id}/edit',                [\App\Controllers\PriceListController::class, 'edit'],        'admin');
$router->post('/price-lists/{id}',                    [\App\Controllers\PriceListController::class, 'update'],      'admin');
$router->post('/price-lists/{id}/delete',             [\App\Controllers\PriceListController::class, 'destroy'],     'admin');
$router->get('/price-lists/{id}/items/export',        [\App\Controllers\PriceListController::class, 'exportItemsCsv'], 'admin');
$router->post('/price-lists/{id}/items/import',       [\App\Controllers\PriceListController::class, 'importItemsCsv'], 'admin');
$router->post('/price-lists/{id}/items',              [\App\Controllers\PriceListController::class, 'upsertItem'],   'admin');
$router->post('/price-lists/{id}/items/{lid}',        [\App\Controllers\PriceListController::class, 'updateItem'],  'admin');
$router->post('/price-lists/{id}/items/{lid}/delete', [\App\Controllers\PriceListController::class, 'destroyItem'],'admin');
$router->post('/price-lists/{id}/tiers',              [\App\Controllers\PriceListController::class, 'addTier'],    'admin');
$router->post('/price-lists/{id}/tiers/{tid}/delete', [\App\Controllers\PriceListController::class, 'removeTier'],'admin');

// Work orders
$router->get('/work-orders',                [\App\Controllers\WorkOrderController::class, 'index']);
$router->get('/work-orders/new',            [\App\Controllers\WorkOrderController::class, 'create'], 'write');
$router->post('/work-orders',               [\App\Controllers\WorkOrderController::class, 'store'],  'write');
$router->get('/work-orders/{id}',           [\App\Controllers\WorkOrderController::class, 'show']);
$router->post('/work-orders/{id}/status',   [\App\Controllers\WorkOrderController::class, 'changeStatus'], 'write');
$router->post('/work-orders/{id}/delete',   [\App\Controllers\WorkOrderController::class, 'destroy'], 'admin');
// AJAX helper for unit-price lookup
$router->get('/api/price',                  [\App\Controllers\WorkOrderController::class, 'priceLookup']);

// Sales
$router->get('/sales',                [\App\Controllers\SaleController::class, 'index']);
$router->get('/sales/new',            [\App\Controllers\SaleController::class, 'create'], 'write');
$router->post('/sales',               [\App\Controllers\SaleController::class, 'store'],  'write');
$router->get('/sales/{id}',           [\App\Controllers\SaleController::class, 'show']);
$router->get('/sales/{id}/print',     [\App\Controllers\SaleController::class, 'printBill']);
$router->post('/sales/{id}/delete',   [\App\Controllers\SaleController::class, 'destroy'],'admin');
$router->get('/api/sale-price',       [\App\Controllers\SaleController::class, 'priceLookup']);

// Receipts
$router->get('/receipts',              [\App\Controllers\ReceiptController::class, 'index']);
$router->get('/receipts/new',          [\App\Controllers\ReceiptController::class, 'create'], 'write');
$router->post('/receipts',             [\App\Controllers\ReceiptController::class, 'store'],  'write');
$router->post('/receipts/{id}/delete', [\App\Controllers\ReceiptController::class, 'destroy'], 'admin');

// Reports
$router->get('/reports/rm-stock',           [\App\Controllers\ReportController::class, 'rmStock']);
$router->get('/reports/rm-ledger',          [\App\Controllers\ReportController::class, 'rmLedger']);
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
