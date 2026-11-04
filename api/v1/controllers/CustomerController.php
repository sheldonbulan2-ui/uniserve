<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/Response.php';
require_once __DIR__ . '/../../config/AuthMiddleware.php';

/**
 * Controller: CustomerController
 * Handles Merchant Discovery, Catalog Browsing, Real-time Cart/Order placement with ACID stock locking,
 * Reservations, and Personal History.
 */
class CustomerController {
    private PDO $db;
    private array $user;

    public function __construct() {
        $this->db = Database::getConnection();
        // Allow customer and merchant roles to test / place orders
        $this->user = AuthMiddleware::requireRole(['customer', 'merchant', 'admin']);
    }

    private function getJsonInput(): array {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : $_POST;
    }

    /**
     * GET /api/v1/customer/merchants
     */
    public function getMerchants(): void {
        $search = trim($_GET['search'] ?? '');
        $sql = 'SELECT id, business_name, business_type, description, address, contact_phone, opening_time, closing_time, is_verified 
            FROM merchant_profiles WHERE is_verified = 1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (business_name LIKE ? OR business_type LIKE ? OR address LIKE ?)';
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }

        $sql .= ' ORDER BY business_name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        Response::success('Merchants retrieved successfully.', $stmt->fetchAll());
    }

    /**
     * GET /api/v1/customer/catalog
     */
    public function getCatalog(): void {
        $merchantId = isset($_GET['merchant_id']) ? (int)$_GET['merchant_id'] : null;
        $itemType = $_GET['item_type'] ?? null;
        $search = trim($_GET['search'] ?? '');

        $sql = "SELECT c.*, m.business_name, cat.name AS category_name, COALESCE(i.stock_quantity, 0) AS stock_quantity, COALESCE(i.unit, 'pcs') AS unit 
            FROM catalog_items c 
            JOIN merchant_profiles m ON c.merchant_id = m.id 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            LEFT JOIN inventory i ON c.id = i.item_id 
            WHERE c.is_available = 1";
        $params = [];

        if ($merchantId) {
            $sql .= ' AND c.merchant_id = ?';
            $params[] = $merchantId;
        }

        if ($itemType && in_array($itemType, ['product', 'service'], true)) {
            $sql .= ' AND c.item_type = ?';
            $params[] = $itemType;
        }

        if ($search !== '') {
            $sql .= ' AND (c.name LIKE ? OR c.description LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= ' ORDER BY c.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        Response::success('Catalog retrieved successfully.', $stmt->fetchAll());
    }

    /**
     * POST /api/v1/customer/orders (ACID Transaction with Stock Deduction)
     */
    public function placeOrder(): void {
        $input = $this->getJsonInput();
        $merchantId = (int)($input['merchant_id'] ?? 0);
        $orderType = in_array($input['order_type'] ?? '', ['dine_in', 'pickup', 'delivery'], true) ? $input['order_type'] : 'pickup';
        $deliveryAddress = trim($input['delivery_address'] ?? '');
        $customerNotes = trim($input['customer_notes'] ?? '');
        $items = $input['items'] ?? []; // Array of ['item_id' => int, 'quantity' => int]

        if ($merchantId <= 0 || empty($items) || !is_array($items)) {
            Response::error('Valid merchant_id and at least one item are required.', 400);
        }

        $this->db->beginTransaction();
        try {
            $subtotal = 0.0;
            $orderItemsToInsert = [];

            foreach ($items as $item) {
                $itemId = (int)($item['item_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 1);
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                // Explicit Lock on Catalog Item and Inventory row
                $stmt = $this->db->prepare("SELECT c.id, c.name, c.price, c.is_available, i.id AS inventory_id, i.stock_quantity 
                    FROM catalog_items c 
                    LEFT JOIN inventory i ON c.id = i.item_id 
                    WHERE c.id = ? AND c.merchant_id = ? FOR UPDATE");
                $stmt->execute([$itemId, $merchantId]);
                $catItem = $stmt->fetch();

                if (!$catItem) {
                    $this->db->rollBack();
                    Response::error("Item #{$itemId} not found in this merchant's catalog.", 404);
                }

                if ((int)$catItem['is_available'] !== 1) {
                    $this->db->rollBack();
                    Response::error("Item '{$catItem['name']}' is currently unavailable.", 400);
                }

                // If product, enforce and deduct stock
                if ($catItem['inventory_id'] !== null) {
                    $currentStock = (int)$catItem['stock_quantity'];
                    if ($currentStock < $qty) {
                        $this->db->rollBack();
                        Response::error("Insufficient stock for '{$catItem['name']}'. Requested: {$qty}, Available: {$currentStock}", 400);
                    }

                    $newStock = $currentStock - $qty;
                    $updStock = $this->db->prepare('UPDATE inventory SET stock_quantity = ? WHERE id = ?');
                    $updStock->execute([$newStock, $catItem['inventory_id']]);

                    // Record inventory audit deduction
                    $invLog = $this->db->prepare("INSERT INTO inventory_logs (inventory_id, performed_by, change_type, quantity_changed, previous_stock, new_stock, remarks) VALUES (?, ?, 'sale_deduction', ?, ?, ?, ?)");
                    $invLog->execute([$catItem['inventory_id'], $this->user['uid'], -$qty, $currentStock, $newStock, "Customer Order deduction"]);
                }

                $unitPrice = (float)$catItem['price'];
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;

                $orderItemsToInsert[] = [
                    'item_id' => $itemId,
                    'name_snapshot' => $catItem['name'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ];
            }

            if (empty($orderItemsToInsert)) {
                $this->db->rollBack();
                Response::error('No valid items in order.', 400);
            }

            $taxAmount = 0.00;
            $totalAmount = $subtotal + $taxAmount;
            $orderNumber = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) . '-' . time();

            // Insert Order
            $ordStmt = $this->db->prepare('INSERT INTO orders (order_number, customer_id, merchant_id, order_type, subtotal, tax_amount, total_amount, order_status, delivery_address, customer_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ordStmt->execute([$orderNumber, $this->user['uid'], $merchantId, $orderType, $subtotal, $taxAmount, $totalAmount, 'pending', $deliveryAddress, $customerNotes]);
            $orderId = (int)$this->db->lastInsertId();

            // Insert Order Items
            $itemStmt = $this->db->prepare('INSERT INTO order_items (order_id, item_id, item_name_snapshot, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($orderItemsToInsert as $oi) {
                $itemStmt->execute([$orderId, $oi['item_id'], $oi['name_snapshot'], $oi['quantity'], $oi['unit_price'], $oi['line_total']]);
            }

            // Create initial payment record
            $payRef = 'PAY-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
            $payStmt = $this->db->prepare("INSERT INTO payments (transaction_ref, order_id, customer_id, merchant_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, 'cash', 'unpaid')");
            $payStmt->execute([$payRef, $orderId, $this->user['uid'], $merchantId, $totalAmount]);

            $this->db->commit();

            Response::success('Order placed successfully.', [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'order_status' => 'pending',
                'payment_ref' => $payRef
            ], 201);

        } catch (Throwable $e) {
            $this->db->rollBack();
            Response::serverError('Order placement failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/customer/reservations
     */
    public function makeReservation(): void {
        $input = $this->getJsonInput();
        $merchantId = (int)($input['merchant_id'] ?? 0);
        $serviceItemId = !empty($input['service_item_id']) ? (int)$input['service_item_id'] : null;
        $date = trim($input['reservation_date'] ?? '');
        $time = trim($input['reservation_time'] ?? '');
        $guestCount = max(1, (int)($input['guest_count'] ?? 1));
        $specialRequests = trim($input['special_requests'] ?? '');

        if ($merchantId <= 0 || $date === '' || $time === '') {
            Response::error('Merchant, reservation date, and reservation time are required.', 400);
        }

        $resCode = 'RES-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)) . '-' . date('md');

        $stmt = $this->db->prepare('INSERT INTO reservations (reservation_code, customer_id, merchant_id, service_item_id, reservation_date, reservation_time, guest_count, status, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$resCode, $this->user['uid'], $merchantId, $serviceItemId, $date, $time, $guestCount, 'pending', $specialRequests]);
        $resId = (int)$this->db->lastInsertId();

        Response::success('Reservation submitted successfully.', [
            'reservation_id' => $resId,
            'reservation_code' => $resCode,
            'status' => 'pending'
        ], 201);
    }

    /**
     * GET /api/v1/customer/orders
     */
    public function getMyOrders(): void {
        $stmt = $this->db->prepare('SELECT o.*, m.business_name 
            FROM orders o 
            JOIN merchant_profiles m ON o.merchant_id = m.id 
            WHERE o.customer_id = ? 
            ORDER BY o.created_at DESC');
        $stmt->execute([$this->user['uid']]);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$ord) {
            $itemStmt = $this->db->prepare('SELECT * FROM order_items WHERE order_id = ?');
            $itemStmt->execute([$ord['id']]);
            $ord['items'] = $itemStmt->fetchAll();
        }

        Response::success('Customer orders retrieved.', $orders);
    }

    /**
     * GET /api/v1/customer/reservations
     */
    public function getMyReservations(): void {
        $stmt = $this->db->prepare('SELECT r.*, m.business_name, c.name AS service_name 
            FROM reservations r 
            JOIN merchant_profiles m ON r.merchant_id = m.id 
            LEFT JOIN catalog_items c ON r.service_item_id = c.id 
            WHERE r.customer_id = ? 
            ORDER BY r.reservation_date DESC, r.reservation_time DESC');
        $stmt->execute([$this->user['uid']]);
        Response::success('Customer reservations retrieved.', $stmt->fetchAll());
    }
}
