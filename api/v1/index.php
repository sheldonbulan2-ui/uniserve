<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors_security.php';
require_once __DIR__ . '/../../config/Response.php';
require_once __DIR__ . '/Router.php';

// Controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/MerchantController.php';
require_once __DIR__ . '/controllers/CustomerController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/PublicApkController.php';

$router = new Router();

// ------------------------------------------------------------------------------
// 1. Public & Auth Routes
// ------------------------------------------------------------------------------
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/register', [AuthController::class, 'register']);
$router->post('/auth/refresh', [AuthController::class, 'refresh']);
$router->post('/auth/logout', [AuthController::class, 'logout']);
$router->get('/public/apk/latest', [PublicApkController::class, 'getLatestRelease']);

// ------------------------------------------------------------------------------
// 2. Merchant Routes (Merchant role only)
// ------------------------------------------------------------------------------
$router->get('/merchant/dashboard', [MerchantController::class, 'dashboard']);
$router->get('/merchant/catalog', [MerchantController::class, 'getCatalog']);
$router->post('/merchant/catalog', [MerchantController::class, 'createCatalogItem']);
$router->post('/merchant/inventory/adjust', [MerchantController::class, 'adjustStock']);
$router->get('/merchant/orders', [MerchantController::class, 'getOrders']);
$router->patch('/merchant/orders/{id}/status', [MerchantController::class, 'updateOrderStatus']);
$router->get('/merchant/reservations', [MerchantController::class, 'getReservations']);
$router->patch('/merchant/reservations/{id}/status', [MerchantController::class, 'updateReservationStatus']);
$router->get('/merchant/reports', [MerchantController::class, 'getReports']);

// ------------------------------------------------------------------------------
// 3. Customer Routes (Customer role or authenticated users)
// ------------------------------------------------------------------------------
$router->get('/customer/merchants', [CustomerController::class, 'getMerchants']);
$router->get('/customer/catalog', [CustomerController::class, 'getCatalog']);
$router->post('/customer/orders', [CustomerController::class, 'placeOrder']);
$router->get('/customer/orders', [CustomerController::class, 'getMyOrders']);
$router->post('/customer/reservations', [CustomerController::class, 'makeReservation']);
$router->get('/customer/reservations', [CustomerController::class, 'getMyReservations']);

// ------------------------------------------------------------------------------
// 4. Admin Routes (Admin role strictly on Web)
// ------------------------------------------------------------------------------
$router->get('/admin/dashboard', [AdminController::class, 'dashboard']);
$router->get('/admin/users', [AdminController::class, 'getUsers']);
$router->patch('/admin/users/{id}/status', [AdminController::class, 'updateUserStatus']);
$router->get('/admin/merchants', [AdminController::class, 'getMerchants']);
$router->patch('/admin/merchants/{id}/verify', [AdminController::class, 'verifyMerchant']);
$router->get('/admin/apk-releases', [AdminController::class, 'getApkReleases']);
$router->post('/admin/apk-releases', [AdminController::class, 'uploadApkRelease']);

// Dispatch request
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
