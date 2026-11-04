-- ==============================================================================
-- UniServe Database Schema for Supabase (PostgreSQL)
-- ==============================================================================
-- Instructions:
-- 1. Open your Supabase Dashboard: https://supabase.com/dashboard
-- 2. Select your Project -> Click "SQL Editor" on the left menu.
-- 3. Paste the contents of this file and click "Run".
-- ==============================================================================

-- Clean up existing tables if recreating (optional)
DROP TABLE IF EXISTS transactions CASCADE;
DROP TABLE IF EXISTS service_requests CASCADE;
DROP TABLE IF EXISTS services CASCADE;
DROP TABLE IF EXISTS businesses CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'customer' CHECK (role IN ('admin', 'business_owner', 'customer')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE categories (
    category_id SERIAL PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Table: businesses
-- --------------------------------------------------------
CREATE TABLE businesses (
    business_id SERIAL PRIMARY KEY,
    owner_id INT NOT NULL REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE,
    category_id INT REFERENCES categories(category_id) ON UPDATE CASCADE ON DELETE SET NULL,
    business_name VARCHAR(150) NOT NULL,
    description TEXT,
    address VARCHAR(255),
    contact_number VARCHAR(30),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_businesses_owner ON businesses(owner_id);
CREATE INDEX idx_businesses_category ON businesses(category_id);
CREATE INDEX idx_businesses_status ON businesses(status);

-- --------------------------------------------------------
-- Table: services
-- --------------------------------------------------------
CREATE TABLE services (
    service_id SERIAL PRIMARY KEY,
    business_id INT NOT NULL REFERENCES businesses(business_id) ON UPDATE CASCADE ON DELETE CASCADE,
    service_name VARCHAR(150) NOT NULL,
    description TEXT,
    price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    availability VARCHAR(20) NOT NULL DEFAULT 'available' CHECK (availability IN ('available', 'unavailable')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_services_business ON services(business_id);
CREATE INDEX idx_services_availability ON services(availability);

-- --------------------------------------------------------
-- Table: service_requests
-- --------------------------------------------------------
CREATE TABLE service_requests (
    request_id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE,
    service_id INT NOT NULL REFERENCES services(service_id) ON UPDATE CASCADE ON DELETE CASCADE,
    request_date TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    preferred_date DATE,
    quantity INT NOT NULL DEFAULT 1,
    total_amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'in_progress', 'completed', 'cancelled', 'rejected')),
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_requests_customer ON service_requests(customer_id);
CREATE INDEX idx_requests_service ON service_requests(service_id);
CREATE INDEX idx_requests_status ON service_requests(status);

-- --------------------------------------------------------
-- Table: transactions
-- --------------------------------------------------------
CREATE TABLE transactions (
    transaction_id SERIAL PRIMARY KEY,
    request_id INT NOT NULL REFERENCES service_requests(request_id) ON UPDATE CASCADE ON DELETE CASCADE,
    amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) DEFAULT 'Not specified',
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'failed', 'refunded')),
    transaction_date TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_transactions_request ON transactions(request_id);
CREATE INDEX idx_transactions_status ON transactions(payment_status);

-- ==============================================================================
-- Initial Seed Data
-- ==============================================================================

-- 1. Categories
INSERT INTO categories (category_id, category_name, description, created_at) VALUES
(1, 'Food and Beverages', 'Cafes, restaurants, and food businesses', '2026-10-10 07:40:03+00'),
(2, 'Retail and Shopping', 'Stores selling products and merchandise', '2026-10-10 07:40:03+00'),
(3, 'Professional Services', 'Professional and consulting services', '2026-10-10 07:40:03+00'),
(4, 'Home Services', 'Cleaning, repairs, and home maintenance', '2026-10-10 07:40:03+00'),
(5, 'Technology Services', 'Computer, IT, and technical services', '2026-10-10 07:40:03+00'),
(6, 'Health and Wellness', 'Wellness and personal care services', '2026-10-10 07:40:03+00'),
(7, 'Other Services', 'Other businesses and service providers', '2026-10-10 07:40:03+00'),
(8, 'Event Services', 'Event planning and party services', '2026-10-11 13:13:09+00');

-- 2. Users (Password is bcrypt hashed: admin password is password / password123 as hashed)
INSERT INTO users (user_id, full_name, email, password, role, created_at) VALUES
(1, 'UniServe Administrator', 'admin@uniserve.com', '$2y$10$wDp706REVot.HDuigKXG2ed.mkYP2SP4PqMO82NfSRS5e56VfsbQe', 'admin', '2026-10-10 07:53:24+00'),
(2, 'Mary Joy Mallillin', 'maryjoy@gmail.com', '$2y$10$KUI2dKZTzBWB0Tgx.SepMOKLA1Q31OHcvlRVEIrTdsxO.WxGv5IqK', 'business_owner', '2026-10-11 05:58:54+00'),
(3, 'Joycerine Galapon', 'joycerine@gmail.com', '$2y$10$.RbsLU1jvn15QHtlCXNoMuU47.P2xOCX3QtXCgq.gcThpgHKzIo/.', 'customer', '2026-10-11 08:31:07+00'),
(4, 'Test Customer', 'test@gmail.com', '$2y$10$/czy0tOOCMH75IehafWCMe5gS1KQNX0q3d20E66GcMAZ33cCKJXV.', 'customer', '2026-10-11 13:11:18+00'),
(5, 'Customer One', 'customer@gmail.com', '$2y$10$cYukgUk4QyHr4g3A1ccknuvooyduDO83hH9tCuhqQEM6lg7HQUAQe', 'customer', '2026-10-11 14:24:34+00'),
(6, 'Maryjoy Mallillin', 'Maryjoymallillin@gmail.com', '$2y$10$3fio0X3ZpuKwR/EmCwcJ3OhGVvJuKm6zLr/BGrB5/zGdIk4evFvfq', 'customer', '2026-10-12 00:40:56+00'),
(7, 'Test User', 'testuser123@example.com', '$2y$10$LAKB9QkUtNFHpSbTSa/huOe/YAsEX0w/7FC3YhzYYbWjWs6sdS/gC', 'customer', '2026-10-13 05:22:51+00');

-- 3. Businesses
INSERT INTO businesses (business_id, owner_id, category_id, business_name, description, address, contact_number, status, created_at) VALUES
(1, 2, 1, 'Bean Yard', 'A small cafe offering coffee, drinks, and snacks.', '123 Tan Street, Manila', '09264362738', 'approved', '2026-10-11 13:20:07+00');

-- 4. Services
INSERT INTO services (service_id, business_id, service_name, description, price, availability, created_at) VALUES
(1, 1, 'Coffee and Snacks', 'Freshly brewed coffee and delicious snacks', 150.00, 'available', '2026-10-11 13:27:00+00');

-- 5. Service Requests
INSERT INTO service_requests (request_id, customer_id, service_id, request_date, preferred_date, quantity, total_amount, status, notes, created_at) VALUES
(1, 4, 1, '2026-10-11 21:31:28+00', '2026-10-11', 1, 150.00, 'completed', 'I would like to order coffee and snacks. Please ensure the items are freshly prepared. Thank you.', '2026-10-11 13:31:28+00'),
(2, 3, 1, '2026-10-11 21:58:13+00', '2026-10-14', 1, 150.00, 'completed', 'Standard order for meetup', '2026-10-11 13:58:13+00'),
(3, 5, 1, '2026-10-11 22:25:35+00', '2026-10-12', 1, 150.00, 'cancelled', 'Order cancelled by customer', '2026-10-11 14:25:35+00'),
(4, 3, 1, '2026-10-11 22:29:04+00', '2026-10-22', 1, 150.00, 'rejected', 'Outside operating area', '2026-10-11 14:29:04+00'),
(5, 6, 1, '2026-10-12 08:42:44+00', '2026-10-15', 4, 600.00, 'cancelled', '', '2026-10-12 00:42:44+00');

-- 6. Transactions
INSERT INTO transactions (transaction_id, request_id, amount, payment_method, payment_status, transaction_date) VALUES
(1, 2, 150.00, 'Cash', 'paid', '2026-10-11 13:58:13+00'),
(2, 3, 150.00, 'Cash', 'pending', '2026-10-11 14:25:35+00'),
(3, 4, 150.00, 'Online Transfer', 'failed', '2026-10-11 14:29:04+00'),
(4, 5, 600.00, 'Cash', 'pending', '2026-10-12 00:42:44+00');

-- ==============================================================================
-- Reset Sequences so auto-increment works smoothly after manual ID inserts
-- ==============================================================================
SELECT setval(pg_get_serial_sequence('categories', 'category_id'), COALESCE((SELECT MAX(category_id) FROM categories), 1));
SELECT setval(pg_get_serial_sequence('users', 'user_id'), COALESCE((SELECT MAX(user_id) FROM users), 1));
SELECT setval(pg_get_serial_sequence('businesses', 'business_id'), COALESCE((SELECT MAX(business_id) FROM businesses), 1));
SELECT setval(pg_get_serial_sequence('services', 'service_id'), COALESCE((SELECT MAX(service_id) FROM services), 1));
SELECT setval(pg_get_serial_sequence('service_requests', 'request_id'), COALESCE((SELECT MAX(request_id) FROM service_requests), 1));
SELECT setval(pg_get_serial_sequence('transactions', 'transaction_id'), COALESCE((SELECT MAX(transaction_id) FROM transactions), 1));
