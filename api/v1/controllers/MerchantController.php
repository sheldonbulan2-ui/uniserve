<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/Response.php';
require_once __DIR__ . '/../../../config/AuthMiddleware.php';

/**
 * Controller: MerchantController
 * Manages Catalog, ACID Inventory with SELECT FOR UPDATE, Orders, Reservations & Reports.
 */
class MerchantController {
    private PDO $db;
    private array $user;
    private int $merchantId;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->user = AuthMiddleware::requireRole(['merchant', 'admin']);
        
        // Resolve merchant_id
        if ($this->user['role'] === 'merchant') {
            $stmt = $this->db->prepare('SELECT id FROM merchant_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$this->user['uid']]);
            $prof = $stmt->fetch();
            if (!$prof) {
                Response::forbidden('Merchant profile not found for this account.');
            }
            $this->merchantId = (int)$prof['id'];
        } else {
            // Admin can act on behalf or specify merchant_id via query
            $this->merchantId = (int)($_GET['merchant_id'] ?? 1);
        }
    }

    private function getJsonInput(): array {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : $_POST;
    }

    /**
     * GET /api/v1/merchant/dashboard
     */
    public function dashboard(): void {
        // Total Sales
        $stmtSales = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue, COUNT(id) AS total_orders 
            FROM orders WHERE merchant_id = ? AND order_status IN ('confirmed', 'preparing', 'ready', 'completed')");
        $stmtSales->execute([$this->merchantId]);
        $salesData = $stmtSales->fetch();

        // Pending Orders
        $stmtPending = $this->db->prepare("SELECT COUNT(id) AS pending_orders FROM orders WHERE merchant_id = ? AND order_status = 'pending'");
        $stmtPending->execute([$this->merchantId]);
        $pendingOrders = (int)$stmtPending->fetchColumn();

        // Upcoming Reservations
        $stmtRes = $this->db->prepare("SELECT COUNT(id) AS upcoming_reservations FROM reservations WHERE merchant_id = ? AND status IN ('pending', 'approved') AND reservation_date >= CURRENT_DATE");
        $stmtRes->execute([$this->merchantId]);
        $upcomingRes = (int)$stmtRes->fetchColumn();

        // Low stock alerts
        $stmtLowStock = $this->db->prepare("SELECT c.name, i.stock_quantity, i.low_stock_threshold, i.unit 
            FROM inventory i 
            JOIN catalog_items c ON i.item_id = c.id 
            WHERE c.merchant_id = ? AND i.stock_quantity <= i.low_stock_threshold");
        $stmtLowStock->execute([$this->merchantId]);
        $lowStockItems = $stmtLowStock->fetchAll();

        // Recent Orders
        $stmtRecent = $this->db->prepare("SELECT o.id, o.order_number, o.order_type, o.total_amount, o.order_status, o.created_at, u.full_name AS customer_name 
            FROM orders o 
            JOIN users u ON o.customer_id = u.id 
            WHERE o.merchant_id = ? 
            ORDER BY o.created_at DESC LIMIT 5");
        $stmtRecent->execute([$this->merchantId]);
        $recentOrders = $stmtRecent->fetchAll();

        Response::success('Merchant dashboard metrics retrieved.', [
            'total_revenue' => (float)$salesData['total_revenue'],
            'total_orders' => (int)$salesData['total_orders'],
            'pending_orders' => $pendingOrders,
            'upcoming_reservations' => $upcomingRes,
            'low_stock_count' => count($lowStockItems),
            'low_stock_alerts' => $lowStockItems,
            'recent_orders' => $recentOrders
        ]);
    }

    /**
     * GET /api/v1/merchant/catalog
     */
    public function getCatalog(): void {
        $stmt = $this->db->prepare("SELECT c.*, cat.name AS category_name, i.stock_quantity, i.unit, i.low_stock_threshold 
            FROM catalog_items c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            LEFT JOIN inventory i ON c.id = i.item_id 
            WHERE c.merchant_id = ? 
            ORDER BY c.created_at DESC");
        $stmt->execute([$this->merchantId]);
        Response::success('Catalog items retrieved.', $stmt->fetchAll());
    }

    /**
     * POST /api/v1/merchant/catalog
     */
    public function createCatalogItem(): void {
        $input = $this->getJsonInput();
        $name = trim($input['name'] ?? '');
        $itemType = in_array($input['item_type'] ?? '', ['product', 'service'], true) ? $input['item_type'] : 'product';
        $price = (float)($input['price'] ?? 0.0);
        $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $sku = trim($input['sku'] ?? '');
        $description = trim($input['description'] ?? '');
        $initialStock = (int)($input['stock_quantity'] ?? 0);
        $unit = trim($input['unit'] ?? 'pcs');

        if ($name === '' || $price <= 0) {
            Response::error('Item name and a valid positive price are required.', 400);
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO catalog_items (merchant_id, category_id, item_type, name, sku, description, price, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$this->merchantId, $categoryId, $itemType, $name, $sku, $description, $price]);
            $itemId = (int)$this->db->lastInsertId();

            if ($itemType === 'product') {
                $invStmt = $this->db->prepare('INSERT INTO inventory (item_id, stock_quantity, low_stock_threshold, unit, last_restocked_at) VALUES (?, ?, 10, ?, NOW())');
                $invStmt->execute([$itemId, $initialStock, $unit]);
                $invId = (int)$this->db->lastInsertId();

                if ($initialStock > 0) {
                    $logStmt = $this->db->prepare("INSERT INTO inventory_logs (inventory_id, performed_by, change_type, quantity_changed, previous_stock, new_stock, remarks) VALUES (?, ?, 'restock', ?, 0, ?, 'Initial inventory creation')");
                    $logStmt->execute([$invId, $this->user['uid'], $initialStock, $initialStock]);
                }
            }

            $this->db->commit();
            Response::success('Catalog item created successfully.', ['item_id' => $itemId], 201);

        } catch (Throwable $e) {
            $this->db->rollBack();
            Response::serverError('Failed to create catalog item: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/merchant/inventory/adjust (ACID Transactional Stock Adjustment)
     */
    public function adjustStock(): void {
        $input = $this->getJsonInput();
        $itemId = (int)($input['item_id'] ?? 0);
        $quantityChange = (int)($input['quantity_change'] ?? 0);
        $changeType = trim($input['change_type'] ?? 'manual_adjustment');
        $remarks = trim($input['remarks'] ?? 'Manual stock adjustment');

        if ($itemId <= 0 || $quantityChange === 0) {
            Response::error('Valid item_id and non-zero quantity_change are required.', 400);
        }

        if (!in_array($changeType, ['restock', 'manual_adjustment', 'wastage'], true)) {
            $changeType = 'manual_adjustment';
        }

        $this->db->beginTransaction();
        try {
            // Lock the inventory row explicitly
            $stmt = $this->db->prepare("SELECT i.id, i.stock_quantity FROM inventory i 
                JOIN catalog_items c ON i.item_id = c.id 
                WHERE i.item_id = ? AND c.merchant_id = ? FOR UPDATE");
            $stmt->execute([$itemId, $this->merchantId]);
            $inv = $stmt->fetch();

            if (!$inv) {
                $this->db->rollBack();
                Response::notFound('Inventory record not found for this merchant item.');
            }

            $prevStock = (int)$inv['stock_quantity'];
            $newStock = $prevStock + $quantityChange;

            if ($newStock < 0) {
                $this->db->rollBack();
                Response::error("Insufficient inventory. Cannot reduce stock below zero (Current: {$prevStock}).", 400);
            }

            // Update inventory
            $upd = $this->db->prepare('UPDATE inventory SET stock_quantity = ?, last_restocked_at = CASE WHEN ? > 0 THEN NOW() ELSE last_restocked_at END WHERE id = ?');
            $upd->execute([$newStock, $quantityChange, $inv['id']]);

            // Insert audit log
            $log = $this->db->prepare('INSERT INTO inventory_logs (inventory_id, performed_by, change_type, quantity_changed, previous_stock, new_stock, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $log->execute([$inv['id'], $this->user['uid'], $changeType, $quantityChange, $prevStock, $newStock, $remarks]);

            $this->db->commit();
            Response::success('Inventory updated successfully.', [
                'item_id' => $itemId,
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'quantity_changed' => $quantityChange
            ]);

        } catch (Throwable $e) {
            $this->db->rollBack();
            Response::serverError('Stock adjustment failed: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/merchant/orders
     */
    public function getOrders(): void {
        $status = $_GET['status'] ?? null;
        $sql = "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone 
            FROM orders o 
            JOIN users u ON o.customer_id = u.id 
            WHERE o.merchant_id = ?";
        $params = [$this->merchantId];

        if ($status && in_array($status, ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
            $sql .= ' AND o.order_status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY o.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Fetch order items for each order
        foreach ($orders as &$order) {
            $itemStmt = $this->db->prepare('SELECT * FROM order_items WHERE order_id = ?');
            $itemStmt->execute([$order['id']]);
            $order['items'] = $itemStmt->fetchAll();
        }

        Response::success('Orders retrieved successfully.', $orders);
    }

    /**
     * PATCH /api/v1/merchant/orders/{id}/status
     */
    public function updateOrderStatus(array $params): void {
        $orderId = (int)($params['id'] ?? 0);
        $input = $this->getJsonInput();
        $newStatus = trim($input['status'] ?? '');

        if (!in_array($newStatus, ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
            Response::error('Invalid order status.', 400);
        }

        $stmt = $this->db->prepare('SELECT id, order_status FROM orders WHERE id = ? AND merchant_id = ? LIMIT 1');
        $stmt->execute([$orderId, $this->merchantId]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::notFound('Order not found.');
        }

        $upd = $this->db->prepare('UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$newStatus, $orderId]);

        Response::success("Order #{$orderId} updated to '{$newStatus}'.", ['order_id' => $orderId, 'status' => $newStatus]);
    }

    /**
     * GET /api/v1/merchant/reservations
     */
    public function getReservations(): void {
        $stmt = $this->db->prepare("SELECT r.*, u.full_name AS customer_name, u.phone AS customer_phone, c.name AS service_name 
            FROM reservations r 
            JOIN users u ON r.customer_id = u.id 
            LEFT JOIN catalog_items c ON r.service_item_id = c.id 
            WHERE r.merchant_id = ? 
            ORDER BY r.reservation_date ASC, r.reservation_time ASC");
        $stmt->execute([$this->merchantId]);
        Response::success('Reservations retrieved.', $stmt->fetchAll());
    }

    /**
     * PATCH /api/v1/merchant/reservations/{id}/status
     */
    public function updateReservationStatus(array $params): void {
        $resId = (int)($params['id'] ?? 0);
        $input = $this->getJsonInput();
        $newStatus = trim($input['status'] ?? '');
        $remarks = trim($input['merchant_remarks'] ?? '');

        if (!in_array($newStatus, ['pending', 'approved', 'in_service', 'completed', 'cancelled', 'no_show'], true)) {
            Response::error('Invalid reservation status.', 400);
        }

        $stmt = $this->db->prepare('SELECT id FROM reservations WHERE id = ? AND merchant_id = ? LIMIT 1');
        $stmt->execute([$resId, $this->merchantId]);
        if (!$stmt->fetch()) {
            Response::notFound('Reservation not found.');
        }

        $upd = $this->db->prepare('UPDATE reservations SET status = ?, merchant_remarks = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$newStatus, $remarks, $resId]);

        Response::success("Reservation #{$resId} status updated to '{$newStatus}'.");
    }

    /**
     * GET /api/v1/merchant/reports
     */
    public function getReports(): void {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $stmt = $this->db->prepare("SELECT DATE(created_at) AS date, COUNT(id) AS order_count, SUM(total_amount) AS revenue 
            FROM orders 
            WHERE merchant_id = ? AND order_status IN ('confirmed', 'completed', 'ready') AND DATE(created_at) BETWEEN ? AND ? 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC");
        $stmt->execute([$this->merchantId, $startDate, $endDate]);
        $dailySales = $stmt->fetchAll();

        Response::success('Reports retrieved.', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'daily_sales' => $dailySales
        ]);
    }
}
