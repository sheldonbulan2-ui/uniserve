<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'merchant') {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$mStmt = $pdo->prepare('SELECT id, business_name, business_type, address FROM merchant_profiles WHERE user_id = ? LIMIT 1');
$mStmt->execute([$userId]);
$merchant = $mStmt->fetch();

if (!$merchant) {
    die('Merchant storefront not provisioned.');
}

$merchantId = (int)$merchant['id'];

// Metrics
$salesStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total_sales, COUNT(id) AS total_orders FROM orders WHERE merchant_id = ? AND order_status IN ('confirmed', 'preparing', 'ready', 'completed')");
$salesStmt->execute([$merchantId]);
$sales = $salesStmt->fetch();

$pendingStmt = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE merchant_id = ? AND order_status = 'pending'");
$pendingStmt->execute([$merchantId]);
$pendingOrders = (int)$pendingStmt->fetchColumn();

// Catalog list
$catStmt = $pdo->prepare("SELECT c.*, COALESCE(i.stock_quantity, 0) AS stock_quantity, COALESCE(i.unit, 'pcs') AS unit 
    FROM catalog_items c 
    LEFT JOIN inventory i ON c.id = i.item_id 
    WHERE c.merchant_id = ? ORDER BY c.id DESC");
$catStmt->execute([$merchantId]);
$catalog = $catStmt->fetchAll();

// Orders list
$ordStmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name 
    FROM orders o 
    JOIN users u ON o.customer_id = u.id 
    WHERE o.merchant_id = ? ORDER BY o.created_at DESC LIMIT 10");
$ordStmt->execute([$merchantId]);
$orders = $ordStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Portal — <?= htmlspecialchars($merchant['business_name']) ?></title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div style="width: 32px; height: 32px; background: var(--color-accent); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #06101E;">M</div>
                <div>
                    <strong style="display: block; font-size: 0.9375rem;"><?= htmlspecialchars($merchant['business_name']) ?></strong>
                    <span class="badge badge-success">Merchant Console</span>
                </div>
            </div>

            <ul class="sidebar-nav">
                <li><a href="merchant_dashboard.php" class="sidebar-link active">📊 Dashboard & Overview</a></li>
                <li><a href="#catalog" class="sidebar-link">📦 Products & Services</a></li>
                <li><a href="#orders" class="sidebar-link">🛒 Order Queue (<?= $pendingOrders ?>)</a></li>
                <li><a href="#reservations" class="sidebar-link">📅 Reservations</a></li>
                <li><a href="index.php" class="sidebar-link">🌐 Public Site</a></li>
            </ul>

            <div class="sidebar-footer">
                <span style="font-size: 0.75rem; color: var(--color-text-muted);"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                <a href="logout.php" class="btn btn-secondary btn-sm">Log Out</a>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <h2 style="font-size: 1.25rem; font-weight: 800;">Merchant Operations Center</h2>
                <div style="display: flex; gap: var(--space-3);">
                    <a href="download.php" class="btn btn-secondary btn-sm">&#129302; Mobile APK</a>
                </div>
            </header>

            <div class="dashboard-content">
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <span class="kpi-title">Gross Revenue</span>
                        <div class="kpi-value">&#8369;<?= number_format((float)$sales['total_sales'], 2) ?></div>
                        <span class="kpi-trend positive">&#10003; Real-time settled</span>
                    </div>

                    <div class="kpi-card">
                        <span class="kpi-title">Total Orders</span>
                        <div class="kpi-value"><?= (int)$sales['total_orders'] ?></div>
                        <span class="kpi-trend positive"><?= $pendingOrders ?> awaiting fulfillment</span>
                    </div>

                    <div class="kpi-card">
                        <span class="kpi-title">Active Products</span>
                        <div class="kpi-value"><?= count($catalog) ?></div>
                        <span class="kpi-trend positive">Live on Marketplace</span>
                    </div>
                </div>

                <!-- Catalog Management Section -->
                <div class="card" id="catalog" style="margin-bottom: var(--space-8);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                        <h3 style="font-size: 1.125rem; font-weight: 700;">Catalog & Inventory Levels</h3>
                    </div>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($catalog as $item): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($item['item_type']) ?></span></td>
                                        <td>&#8369;<?= number_format((float)$item['price'], 2) ?></td>
                                        <td><?= (int)$item['stock_quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                                        <td>
                                            <?php if ($item['is_available']): ?>
                                                <span class="badge badge-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order Queue Section -->
                <div class="card" id="orders">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: var(--space-4);">Recent Customer Orders</h3>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Placed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $ord): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ord['order_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($ord['customer_name']) ?></td>
                                        <td><?= strtoupper($ord['order_type']) ?></td>
                                        <td>&#8369;<?= number_format((float)$ord['total_amount'], 2) ?></td>
                                        <td><span class="badge badge-warning"><?= htmlspecialchars($ord['order_status']) ?></span></td>
                                        <td><?= htmlspecialchars($ord['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
