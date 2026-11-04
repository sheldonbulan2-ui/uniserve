<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Fetch available merchants and services
$mStmt = $pdo->query('SELECT id, business_name, business_type, address FROM merchant_profiles WHERE is_verified = 1');
$merchants = $mStmt->fetchAll();

$itemsStmt = $pdo->query("SELECT c.*, m.business_name 
    FROM catalog_items c 
    JOIN merchant_profiles m ON c.merchant_id = m.id 
    WHERE c.is_available = 1 ORDER BY c.id DESC LIMIT 12");
$catalog = $itemsStmt->fetchAll();

// Customer active orders
$myOrdersStmt = $pdo->prepare('SELECT o.*, m.business_name 
    FROM orders o 
    JOIN merchant_profiles m ON o.merchant_id = m.id 
    WHERE o.customer_id = ? ORDER BY o.created_at DESC');
$myOrdersStmt->execute([$userId]);
$myOrders = $myOrdersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Hub — UniServe Marketplace</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div style="width: 32px; height: 32px; background: var(--color-primary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #FFF;">C</div>
                <div>
                    <strong style="display: block; font-size: 0.9375rem;">UniServe Consumer</strong>
                    <span class="badge badge-info">Customer Account</span>
                </div>
            </div>

            <ul class="sidebar-nav">
                <li><a href="customer_dashboard.php" class="sidebar-link active">🛍️ Marketplace</a></li>
                <li><a href="#orders" class="sidebar-link">📦 My Orders (<?= count($myOrders) ?>)</a></li>
                <li><a href="download.php" class="sidebar-link">📱 Mobile APK</a></li>
                <li><a href="index.php" class="sidebar-link">🌐 Public Site</a></li>
            </ul>

            <div class="sidebar-footer">
                <span style="font-size: 0.75rem; color: var(--color-text-muted);"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                <a href="logout.php" class="btn btn-secondary btn-sm">Log Out</a>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <h2 style="font-size: 1.25rem; font-weight: 800;">Browse Merchants & Services</h2>
                <div>
                    <a href="download.php" class="btn btn-primary btn-sm">&#129302; Use Native Android App</a>
                </div>
            </header>

            <div class="dashboard-content">
                <!-- Merchants Banner -->
                <div style="margin-bottom: var(--space-8);">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: var(--space-4);">Featured Partners</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                        <?php foreach ($merchants as $m): ?>
                            <div class="card" style="padding: var(--space-4);">
                                <h4 style="font-size: 1rem; font-weight: 700;"><?= htmlspecialchars($m['business_name']) ?></h4>
                                <span class="badge badge-success" style="margin: 6px 0;"><?= htmlspecialchars($m['business_type']) ?></span>
                                <p style="font-size: 0.8125rem; color: var(--color-text-muted);"><?= htmlspecialchars($m['address'] ?? 'Online Store') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live Marketplace Catalog Items -->
                <div style="margin-bottom: var(--space-8);">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: var(--space-4);">Available Products & Services</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-5);">
                        <?php foreach ($catalog as $item): ?>
                            <div class="card">
                                <span class="badge badge-info" style="margin-bottom: 8px;"><?= htmlspecialchars($item['item_type']) ?></span>
                                <h4 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 4px;"><?= htmlspecialchars($item['name']) ?></h4>
                                <small style="color: var(--color-text-dim); display: block; margin-bottom: 12px;">by <?= htmlspecialchars($item['business_name']) ?></small>
                                <p style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.5; margin-bottom: 16px;">
                                    <?= htmlspecialchars($item['description'] ?? 'High quality offering from verified merchant.') ?>
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border-subtle); padding-top: 12px;">
                                    <strong style="font-size: 1.25rem; color: var(--color-cyan);">&#8369;<?= number_format((float)$item['price'], 2) ?></strong>
                                    <a href="download.php" class="btn btn-secondary btn-sm">Order on Mobile &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- My Orders Section -->
                <div class="card" id="orders">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: var(--space-4);">My Purchase & Booking History</h3>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Merchant</th>
                                    <th>Type</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myOrders)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 30px;">
                                            No active orders yet. Download the APK or browse the marketplace to place an order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myOrders as $ord): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($ord['order_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($ord['business_name']) ?></td>
                                            <td><?= strtoupper($ord['order_type']) ?></td>
                                            <td>&#8369;<?= number_format((float)$ord['total_amount'], 2) ?></td>
                                            <td><span class="badge badge-warning"><?= htmlspecialchars($ord['order_status']) ?></span></td>
                                            <td><?= htmlspecialchars($ord['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
