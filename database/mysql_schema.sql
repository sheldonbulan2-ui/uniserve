-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2026 at 06:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `uniserve_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `business_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `business_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`business_id`, `owner_id`, `category_id`, `business_name`, `description`, `address`, `contact_number`, `status`, `created_at`) VALUES
(1, 2, 1, 'Bean Yard', 'A small cafe offering coffee, drinks, and snacks.', '123 Tan Street, Manila', '09264362738', 'approved', '2026-10-11 13:20:07');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Food and Beverages', 'cafes, and food businesses', '2026-10-10 07:40:03'),
(2, 'Retail and Shopping', 'Stores selling products and merchandise', '2026-10-10 07:40:03'),
(3, 'Professional Services', 'Professional and consulting services', '2026-10-10 07:40:03'),
(4, 'Home Services', 'Cleaning, repairs, and home maintenance', '2026-10-10 07:40:03'),
(5, 'Technology Services', 'Computer, IT, and technical services', '2026-10-10 07:40:03'),
(6, 'Health and Wellness', 'Wellness and personal care services', '2026-10-10 07:40:03'),
(7, 'Other Services', 'Other businesses and service providers', '2026-10-10 07:40:03'),
(8, 'Event Services', 'Event planning and party services', '2026-10-11 13:13:09');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `availability` enum('available','unavailable') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `business_id`, `service_name`, `description`, `price`, `availability`, `created_at`) VALUES
(1, 1, 'Coffee and Snacks', 'Freshly brewed coffee and delicious snacks', 150.00, 'available', '2026-10-11 13:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `request_date` datetime DEFAULT current_timestamp(),
  `preferred_date` date DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','accepted','in_progress','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`request_id`, `customer_id`, `service_id`, `request_date`, `preferred_date`, `quantity`, `total_amount`, `status`, `notes`, `created_at`) VALUES
(1, 4, 1, '2026-10-11 21:31:28', '2026-10-11', 1, 150.00, 'completed', 'I would like to order coffee and snacks. Please ensure the items are freshly prepared. Thank you.', '2026-10-11 13:31:28'),
(2, 3, 1, '2026-10-11 21:58:13', '2026-10-14', 1, 150.00, 'completed', 'hjfghfjkhlk', '2026-10-11 13:58:13'),
(3, 5, 1, '2026-10-11 22:25:35', '2026-10-12', 1, 150.00, 'cancelled', 'bghdkjliy', '2026-10-11 14:25:35'),
(4, 3, 1, '2026-10-11 22:29:04', '2026-10-22', 1, 150.00, 'rejected', 'hgytsuyg', '2026-10-11 14:29:04'),
(5, 6, 1, '2026-10-12 08:42:44', '2026-10-15', 4, 600.00, 'cancelled', '', '2026-10-12 00:42:44');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `request_id`, `amount`, `payment_method`, `payment_status`, `transaction_date`) VALUES
(1, 2, 150.00, 'Not specified', 'paid', '2026-10-11 13:58:13'),
(2, 3, 150.00, 'Not specified', 'pending', '2026-10-11 14:25:35'),
(3, 4, 150.00, 'Not specified', 'failed', '2026-10-11 14:29:04'),
(4, 5, 600.00, 'Not specified', 'pending', '2026-10-12 00:42:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','business_owner','customer') NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'UniServe Administrator', 'admin@uniserve.com', '$2y$10$wDp706REVot.HDuigKXG2ed.mkYP2SP4PqMO82NfSRS5e56VfsbQe', 'admin', '2026-10-10 07:53:24'),
(2, 'Mary Joy Mallillin', 'maryjoy@gmail.com', '$2y$10$KUI2dKZTzBWB0Tgx.SepMOKLA1Q31OHcvlRVEIrTdsxO.WxGv5IqK', 'business_owner', '2026-10-11 05:58:54'),
(3, 'Joycerine Galapon', 'joycerine@gmail.com', '$2y$10$.RbsLU1jvn15QHtlCXNoMuU47.P2xOCX3QtXCgq.gcThpgHKzIo/.', 'customer', '2026-10-11 08:31:07'),
(4, 'Test Customer', 'test@gmail.com', '$2y$10$/czy0tOOCMH75IehafWCMe5gS1KQNX0q3d20E66GcMAZ33cCKJXV.', 'customer', '2026-10-11 13:11:18'),
(5, 'Customer One', 'customer@gmail.com', '$2y$10$cYukgUk4QyHr4g3A1ccknuvooyduDO83hH9tCuhqQEM6lg7HQUAQe', 'customer', '2026-10-11 14:24:34'),
(6, 'Maryjoy Mallillin', 'Maryjoymallillin@gmail.com', '$2y$10$3fio0X3ZpuKwR/EmCwcJ3OhGVvJuKm6zLr/BGrB5/zGdIk4evFvfq', 'customer', '2026-10-12 00:40:56'),
(7, 'Test User', 'testuser123@example.com', '$2y$10$LAKB9QkUtNFHpSbTSa/huOe/YAsEX0w/7FC3YhzYYbWjWs6sdS/gC', 'customer', '2026-10-13 05:22:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`business_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `business_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `businesses_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `businesses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `service_requests_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`request_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
