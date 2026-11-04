-- ==============================================================================
-- UniServe: Unified Business and Service Management Application
-- Centralized 3NF Relational Database Schema (MySQL 8.0+ / MariaDB 10.4+)
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS apk_releases;
DROP TABLE IF EXISTS auth_tokens;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS inventory_logs;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS catalog_items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS merchant_profiles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. Table: users
-- ------------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    role ENUM('admin', 'merchant', 'customer') NOT NULL DEFAULT 'customer',
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(255) NULL,
    status ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'active',
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table: merchant_profiles
-- ------------------------------------------------------------------------------
CREATE TABLE merchant_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    business_name VARCHAR(150) NOT NULL,
    business_type VARCHAR(100) NOT NULL DEFAULT 'General Services',
    description TEXT NULL,
    address VARCHAR(255) NULL,
    contact_phone VARCHAR(30) NULL,
    opening_time TIME NULL,
    closing_time TIME NULL,
    is_verified BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_merchant_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_merchant_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Table: categories
-- ------------------------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NULL,
    name VARCHAR(100) NOT NULL,
    category_type ENUM('product', 'service') NOT NULL DEFAULT 'product',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_category_merchant FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_categories_type (category_type),
    INDEX idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Table: catalog_items
-- ------------------------------------------------------------------------------
CREATE TABLE catalog_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    category_id INT NULL,
    item_type ENUM('product', 'service') NOT NULL DEFAULT 'product',
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_url VARCHAR(255) NULL,
    duration_minutes INT NULL,
    max_capacity INT NULL,
    is_available BOOLEAN NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_catalog_merchant FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_catalog_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_catalog_type (item_type),
    INDEX idx_catalog_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Table: inventory
-- ------------------------------------------------------------------------------
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL UNIQUE,
    stock_quantity INT NOT NULL DEFAULT 0,
    reserved_quantity INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 10,
    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
    last_restocked_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_item FOREIGN KEY (item_id) REFERENCES catalog_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_inventory_stock (stock_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Table: inventory_logs
-- ------------------------------------------------------------------------------
CREATE TABLE inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    performed_by INT NOT NULL,
    change_type ENUM('restock', 'sale_deduction', 'cancellation_restore', 'manual_adjustment', 'wastage') NOT NULL,
    quantity_changed INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    remarks VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invlog_inventory FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_invlog_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_invlog_type (change_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. Table: orders
-- ------------------------------------------------------------------------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    merchant_id INT NOT NULL,
    order_type ENUM('dine_in', 'pickup', 'delivery') NOT NULL DEFAULT 'pickup',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    order_status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    delivery_address TEXT NULL,
    customer_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_orders_merchant FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_orders_status (order_status),
    INDEX idx_orders_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. Table: order_items
-- ------------------------------------------------------------------------------
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name_snapshot VARCHAR(150) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_orderitems_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_orderitems_item FOREIGN KEY (item_id) REFERENCES catalog_items(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. Table: reservations
-- ------------------------------------------------------------------------------
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_code VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    merchant_id INT NOT NULL,
    service_item_id INT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    guest_count INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'approved', 'in_service', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'pending',
    special_requests TEXT NULL,
    merchant_remarks TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_res_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_res_merchant FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_res_service FOREIGN KEY (service_item_id) REFERENCES catalog_items(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_res_status (status),
    INDEX idx_res_datetime (reservation_date, reservation_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. Table: payments
-- ------------------------------------------------------------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_ref VARCHAR(80) NOT NULL UNIQUE,
    order_id INT NULL,
    reservation_id INT NULL,
    customer_id INT NOT NULL,
    merchant_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash', 'gcash', 'maya', 'card', 'bank_transfer') NOT NULL DEFAULT 'cash',
    payment_proof_url VARCHAR(255) NULL,
    payment_status ENUM('unpaid', 'pending_verification', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'unpaid',
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pay_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pay_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pay_merchant FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_pay_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. Table: auth_tokens
-- ------------------------------------------------------------------------------
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    refresh_token_hash CHAR(64) NOT NULL,
    client_platform ENUM('web', 'android') NOT NULL DEFAULT 'web',
    device_info VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    revoked BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_token_hash (refresh_token_hash),
    INDEX idx_token_status (expires_at, revoked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. Table: apk_releases
-- ------------------------------------------------------------------------------
CREATE TABLE apk_releases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version_name VARCHAR(20) NOT NULL,
    version_code INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size_bytes BIGINT NOT NULL DEFAULT 0,
    sha256_checksum CHAR(64) NOT NULL,
    release_notes TEXT NULL,
    is_latest BOOLEAN NOT NULL DEFAULT 1,
    download_count INT NOT NULL DEFAULT 0,
    uploaded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_apk_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_apk_latest (is_latest)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. Table: audit_logs
-- ------------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    details JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- SEED DATA (Admin, Merchant, Customer with Bcrypt password: 'password123')
-- ------------------------------------------------------------------------------
INSERT INTO users (id, uuid, role, full_name, email, phone, password_hash, status, created_at) VALUES
(1, 'a0000000-0000-0000-0000-000000000001', 'admin', 'UniServe Administrator', 'admin@uniserve.com', '09123456789', '$2y$10$qCN028Kmx9ve1Z4lZelB5ejA.XCnyDxaRaW.ibMkzlKneazgErJYG', 'active', NOW()),
(2, 'a0000000-0000-0000-0000-000000000002', 'merchant', 'Mary Joy Mallillin', 'maryjoy@gmail.com', '09264362738', '$2y$10$qCN028Kmx9ve1Z4lZelB5ejA.XCnyDxaRaW.ibMkzlKneazgErJYG', 'active', NOW()),
(3, 'a0000000-0000-0000-0000-000000000003', 'customer', 'Joycerine Galapon', 'joycerine@gmail.com', '09179876543', '$2y$10$qCN028Kmx9ve1Z4lZelB5ejA.XCnyDxaRaW.ibMkzlKneazgErJYG', 'active', NOW());

INSERT INTO merchant_profiles (id, user_id, business_name, business_type, description, address, contact_phone, opening_time, closing_time, is_verified, created_at) VALUES
(1, 2, 'Bean Yard Cafe & Resto', 'Food & Dining / Cafe', 'Artisanal coffee, pastries, comfort food, and table reservations in Manila.', '123 Tan Street, Manila', '09264362738', '08:00:00', '22:00:00', 1, NOW());

INSERT INTO categories (id, merchant_id, name, category_type, is_active) VALUES
(1, 1, 'Beverages & Coffee', 'product', 1),
(2, 1, 'Meals & Snacks', 'product', 1),
(3, 1, 'Event & Table Booking', 'service', 1);

INSERT INTO catalog_items (id, merchant_id, category_id, item_type, name, sku, description, price, is_available) VALUES
(1, 1, 1, 'product', 'Artisan Espresso Latte', 'BY-LATTE-01', 'Freshly brewed double espresso with velvety steamed milk.', 150.00, 1),
(2, 1, 1, 'product', 'Cold Brew Reserve', 'BY-CBR-02', 'Slow-steeped 18-hour cold brew coffee.', 175.00, 1),
(3, 1, 2, 'product', 'Smoked Bacon & Waffle Plate', 'BY-WAF-01', 'Crispy Belgian waffles topped with smoked bacon and maple syrup.', 260.00, 1),
(4, 1, 3, 'service', 'VIP Lounge Table Reservation', 'BY-RES-VIP', 'Exclusive reserved seating for meetings and dining (up to 6 guests).', 500.00, 1);

INSERT INTO inventory (item_id, stock_quantity, reserved_quantity, low_stock_threshold, unit, last_restocked_at) VALUES
(1, 100, 0, 15, 'cups', NOW()),
(2, 85, 0, 10, 'bottles', NOW()),
(3, 40, 0, 8, 'servings', NOW());

INSERT INTO apk_releases (id, version_name, version_code, file_name, file_path, file_size_bytes, sha256_checksum, release_notes, is_latest, download_count, uploaded_by, created_at) VALUES
(1, 'v1.0.0', 1, 'UniServe-v1.0.apk', 'downloads/UniServe-v1.0.apk', 15674832, 'a5536eb99b760723bcd6845faf2a4db2c0fe95e4f48b066cf640c4e70e9a5697', 'Official Production Release for Merchants and Customers with native REST API booking and ordering.', 1, 14, 1, NOW());
