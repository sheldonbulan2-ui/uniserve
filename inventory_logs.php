<?php
/**
 * UniServe Enterprise Admin — Inventory Audit & Transaction Logs
 */
$pageTitle = "Inventory Logs & Stock Audit";
$pageSubtitle = "ACID-protected transactional stock adjustments, sales deductions, and restock logs";
$activeNav = "inventory_logs";

require_once __DIR__ . '/includes/admin_header.php';

// Fetch inventory logs with user and item names
$sql = "SELECT l.*, i.unit, c.name AS item_name, u.full_name AS performed_by_name, m.business_name 
    FROM inventory_logs l 
    JOIN inventory i ON l.inventory_id = i.id 
    JOIN catalog_items c ON i.item_id = c.id 
    LEFT JOIN merchant_profiles m ON c.merchant_id = m.id 
    LEFT JOIN users u ON l.performed_by = u.id 
    ORDER BY l.created_at DESC LIMIT 50";

$res = $pdo->query($sql);
$logs = $res ? $res->fetchAll() : [];
?>

<div class="admin-toolbar" style="margin-bottom: var(--space-6);">
    <div class="toolbar-left">
        <h3 style="font-size: 1.125rem; font-weight: 700;">Global Inventory Movement Audit</h3>
    </div>
    <div class="toolbar-right">
        <span class="admin-badge-count"><?= count($logs) ?> Recent Events</span>
    </div>
</div>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Event ID</th>
                <th>Item & Storefront</th>
                <th>Change Type</th>
                <th>Previous Stock</th>
                <th>Qty Delta</th>
                <th>New Stock</th>
                <th>Performed By</th>
                <th>Remarks</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--color-text-muted); padding: 30px;">
                        No stock movement logs recorded yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><strong>#LOG-<?= (int)$log['id'] ?></strong></td>
                        <td>
                            <strong><?= htmlspecialchars($log['item_name']) ?></strong>
                            <small style="display: block; color: var(--color-text-dim);"><?= htmlspecialchars($log['business_name'] ?? 'System') ?></small>
                        </td>
                        <td>
                            <?php if ($log['change_type'] === 'restock'): ?>
                                <span class="badge badge-success">Restock</span>
                            <?php elseif ($log['change_type'] === 'sale_deduction'): ?>
                                <span class="badge badge-warning">Sale Deduction</span>
                            <?php else: ?>
                                <span class="badge badge-info"><?= htmlspecialchars($log['change_type']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$log['previous_stock'] ?> <?= htmlspecialchars($log['unit']) ?></td>
                        <td>
                            <?php if ($log['quantity_changed'] > 0): ?>
                                <strong style="color: var(--color-success);">+<?= (int)$log['quantity_changed'] ?></strong>
                            <?php else: ?>
                                <strong style="color: var(--color-danger);"><?= (int)$log['quantity_changed'] ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= (int)$log['new_stock'] ?></strong> <?= htmlspecialchars($log['unit']) ?></td>
                        <td><?= htmlspecialchars($log['performed_by_name'] ?? 'System Automation') ?></td>
                        <td><small style="color: var(--color-text-muted);"><?= htmlspecialchars($log['remarks'] ?? '—') ?></small></td>
                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
