-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 26, 2026 at 12:47 pm
-- Server version: 5.5.8
-- PHP Version: 5.3.5

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `egg_farm_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `egg_inventory`
--

CREATE TABLE IF NOT EXISTS `egg_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` varchar(30) NOT NULL,
  `harvest_date` date NOT NULL,
  `harvest_time` time NOT NULL,
  `egg_size` enum('XS','Small','Medium','Large','XL','Jumbo','Super Jumbo','Double Yolk') NOT NULL,
  `quantity` int(11) NOT NULL,
  `current_stock` int(11) NOT NULL,
  `movement_type` enum('Stock In','Stock Out','Adjustment') NOT NULL,
  `reason` enum('Harvest','Reservation','Damaged','Adjustment') NOT NULL DEFAULT 'Harvest',
  `date_logged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=14 ;

--
-- Dumping data for table `egg_inventory`
--

INSERT INTO `egg_inventory` (`id`, `batch_id`, `harvest_date`, `harvest_time`, `egg_size`, `quantity`, `current_stock`, `movement_type`, `reason`, `date_logged`) VALUES
(1, 'EG20260728-001', '2026-07-28', '08:00:00', 'Large', 3500, 3500, 'Stock In', 'Harvest', '2026-07-28 13:28:00'),
(2, 'EG20260728-002', '2026-07-28', '11:00:00', 'Medium', 2800, 2800, 'Stock In', 'Harvest', '2026-07-28 13:28:00'),
(3, 'EG20260728-003', '2026-07-28', '14:00:00', 'XL', 1900, 1900, 'Stock In', 'Harvest', '2026-07-28 13:28:00'),
(4, 'EG20260728-004', '2026-07-28', '16:00:00', 'Jumbo', 700, 700, 'Stock In', 'Harvest', '2026-07-28 13:28:00'),
(5, 'EG20260728-005', '2026-07-28', '16:30:00', 'Large', 250, 3250, 'Stock Out', 'Reservation', '2026-07-28 13:28:00'),
(6, 'EG20260728-006', '2026-07-28', '17:00:00', 'Medium', 50, 2750, 'Stock Out', 'Damaged', '2026-07-28 13:28:00'),
(7, 'EG20260728-007', '2026-07-28', '17:30:00', 'Large', 100, 3350, 'Adjustment', 'Adjustment', '2026-07-28 13:28:00'),
(8, 'EG202607281804', '2026-07-28', '18:04:00', 'XS', 20, 20, 'Adjustment', 'Adjustment', '2026-07-28 18:05:07'),
(9, 'EG20260730-001', '2026-07-30', '19:23:00', 'XS', 500, 520, 'Stock In', 'Harvest', '2026-07-30 19:24:29'),
(10, 'EG20260806-001', '2026-08-06', '16:07:00', 'XS', 600, 1120, 'Stock In', 'Harvest', '2026-08-06 16:07:32'),
(11, 'EG20260806-002', '2026-08-06', '16:30:00', 'XS', 66, 1186, 'Stock In', 'Harvest', '2026-08-06 16:31:08'),
(12, 'EG20260806-003', '2026-08-06', '17:08:00', 'Small', 400, 400, 'Stock In', 'Harvest', '2026-08-06 17:09:15'),
(13, 'EG20260824-001', '2026-08-24', '18:27:00', 'XS', 20, 1166, 'Stock Out', 'Reservation', '2026-08-24 18:27:32');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=9 ;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `description`, `type`, `is_read`, `created_at`) VALUES
(1, 6, 'Bundle order submitted!', 'Your bundle of 50 total tray(s) is processing.', 'success', 1, '2026-08-10 17:33:11'),
(2, 6, 'Bundle order submitted!', 'Your bundle of 26 total tray(s) is processing.', 'success', 1, '2026-08-13 23:16:05'),
(3, 6, 'Bundle order submitted!', 'Your bundle of 46 total tray(s) is processing.', 'success', 1, '2026-08-13 23:24:28'),
(4, 6, 'Reservation submitted!', 'Reservation RES-20260814-001 with 50 total tray(s) is waiting for manager confirmation.', 'success', 1, '2026-08-14 11:23:52'),
(5, 6, 'Reservation submitted!', 'Reservation RES-20260814-002 with 70 total tray(s) is waiting for manager confirmation.', 'success', 1, '2026-08-14 11:42:29'),
(6, 6, 'Reservation submitted!', 'Reservation RES-20260814-002 with 30 total tray(s) is waiting for manager confirmation.', 'success', 1, '2026-08-14 11:47:51'),
(7, 6, 'Reservation submitted!', 'Reservation RES-20260818-001 with 500 total tray(s) is waiting for manager confirmation.', 'success', 1, '2026-08-18 14:12:52'),
(8, 7, 'Reservation submitted!', 'Reservation RES-20260819-001 with 67 total tray(s) is waiting for manager confirmation.', 'success', 1, '2026-08-19 10:12:29');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE IF NOT EXISTS `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `delivery_address` text,
  `egg_type` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `delivery_method` varchar(50) DEFAULT NULL,
  `reservation_date` date DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) DEFAULT NULL,
  `reservation_code` varchar(30) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `reserved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=9 ;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `customer_name`, `contact_number`, `delivery_address`, `egg_type`, `quantity`, `delivery_method`, `reservation_date`, `total_price`, `created_at`, `user_id`, `reservation_code`, `status`, `reserved_at`) VALUES
(1, 'Alliana Diokno', '09555937920', 'Diokno Highway, Payapa Ilaya, Lemery, Batangas, Calabarzon, 4209, Philippines', 'Extra Small', 50, 'Delivery', '2026-08-11', 7000.00, '2026-08-10 17:33:11', 6, NULL, 'Pending', NULL),
(2, 'Alliana Diokno', '09564326541', 'bagong sikat', 'Small', 26, 'Pickup', '2026-08-20', 3900.00, '2026-08-13 23:16:05', 6, NULL, 'Pending', NULL),
(3, 'Alliana Diokno', '09876543212', 'Legarda Street, Barangay 410, Sampaloc, Fourth District, Manila, Capital District, Metro Manila, 1213, Philippines', 'Extra Large', 46, 'Delivery', '2026-08-28', 10810.00, '2026-08-13 23:24:28', 6, NULL, 'Pending', NULL),
(4, 'Alliana Diokno', '09234567890', '2551, Legarda Street, Barangay 410, Sampaloc, Fourth District, Manila, Capital District, Metro Manila, 1005, Philippines', 'Extra Small', 50, 'Delivery', '2026-08-20', 7000.00, '2026-08-14 11:23:52', 6, 'RES-20260814-001', 'Pending', NULL),
(5, 'Alliana Diokno', '09876543456', 'Mendiola Extension, Barangay 831, Paco, Fifth District, Manila, Capital District, Metro Manila, 1007, Philippines', 'Jumbo', 70, 'Pickup', '2026-08-20', 17850.00, '2026-08-14 11:42:29', 6, '0', 'Pending', '0000-00-00 00:00:00'),
(6, 'Alliana Diokno', '09456872453', 'Paltoc Street, Barangay 410, Sampaloc, Fourth District, Manila, Capital District, Metro Manila, 1213, Philippines', 'Super Jumbo', 30, 'Delivery', '2026-08-29', 8100.00, '2026-08-14 11:47:51', 6, 'RES-20260814-002', 'Confirmed', '2026-08-14 11:47:51'),
(7, 'Alliana Diokno', '09879876543', 'Taft Avenue, Ermita, Fifth District, Manila, Capital District, Metro Manila, 1000, Philippines', 'Double Yolk', 500, 'Delivery', '2026-08-31', 160000.00, '2026-08-18 14:12:52', 6, 'RES-20260818-001', 'Confirmed', '2026-08-18 14:12:52'),
(8, 'Sherly Diokno', '09653489266', 'Valderama Street, Barangay 276, San Nicolas, Third District, Manila, Capital District, Metro Manila, 1010, Philippines', 'Extra Large', 67, 'Delivery', '2026-08-29', 15745.00, '2026-08-19 10:12:29', 7, 'RES-20260819-001', 'Confirmed', '2026-08-19 10:12:29');

-- --------------------------------------------------------

--
-- Table structure for table `supply_inventory`
--

CREATE TABLE IF NOT EXISTS `supply_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` varchar(50) NOT NULL,
  `item_category` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `current_stock` int(11) NOT NULL DEFAULT '0',
  `action_type` varchar(50) NOT NULL,
  `reason` text,
  `date_logged` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=18 ;

--
-- Dumping data for table `supply_inventory`
--

INSERT INTO `supply_inventory` (`id`, `batch_id`, `item_category`, `item_name`, `quantity`, `current_stock`, `action_type`, `reason`, `date_logged`) VALUES
(1, 'FD20260730-001', 'Feeds', 'MCP', 200, 200, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(2, 'MD20260730-001', 'Medicine', 'ADEC', 20, 20, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(3, 'TR20260730-001', 'Trays', 'Plastic Trays', 5000, 5000, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(4, 'TR20260730-002', 'Trays', 'Plastic Trays', 5000, 10000, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(5, 'TR20260730-003', 'Trays', 'Paper Trays', 10000, 10000, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(6, 'FD20260730-002', 'Feeds', 'MCP', 500, 700, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(7, 'FD20260730-003', 'Feeds', 'MCP', 600, 1300, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(8, 'FD20260730-004', 'Feeds', 'MCP', 500, 1800, 'Stock In', 'Purchase', '0000-00-00 00:00:00'),
(9, 'FD20260730-005', 'Feeds', 'MCP', 300, 2100, 'Stock In', 'Purchase', '2026-07-30 13:19:33'),
(10, 'FD20260730-006', 'Feeds', 'MCP', 400, 2500, 'Stock In', 'Purchase', '2026-07-30 19:23:30'),
(11, 'FD20260805-001', 'Feeds', 'MCP', 400, 2900, 'Stock In', 'Purchase', '2026-08-05 12:14:32'),
(12, 'TR20260805-001', 'Trays', 'Plastic Trays', 400, 10400, 'Stock In', 'Purchase', '2026-08-05 12:16:23'),
(13, 'FD20260806-001', 'Feeds', 'MCP', 500, 3400, 'Stock In', 'Purchase', '2026-08-06 16:07:10'),
(14, 'FD20260806-002', 'Feeds', 'MCP', 400, 3800, 'Stock In', 'Purchase', '2026-08-06 16:24:33'),
(15, 'TR20260806-001', 'Trays', 'Plastic Trays', 222, 10622, 'Stock In', 'Purchase', '2026-08-06 16:27:06'),
(16, 'MD20260806-001', 'Medicine', 'ADEC', 1111, 1131, 'Stock In', 'Purchase', '2026-08-06 16:35:17'),
(17, 'FD20260806-003', 'Feeds', 'MCP', 444, 4244, 'Stock In', 'Purchase', '2026-08-06 17:06:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','manager','customer') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `username_2` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `email_2` (`email`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'Owner Account', 'owner', 'owner@gmail.com', '5be057accb25758101fa5eadbbd79503', 'owner', 'active', '2026-06-25 19:04:21'),
(2, 'Manager Account', 'manager', 'manager@gmail.com', '0795151defba7a4b5dfa89170de46277', 'manager', 'active', '2026-06-25 19:04:21'),
(3, 'Aleah Mae Diokno', 'Eyah', 'aleahdiokno@gmail.com', '636ef033dc643ebf88c62466d302ffb7', 'customer', 'active', '2026-06-25 19:19:22'),
(4, 'John Rennel Masangkay', 'rennel', 'rennel@gmail.com', '3b5fa01bb12cd125ecc7855803c1690b', 'customer', 'active', '2026-06-25 20:17:45'),
(5, 'Fyden Millard Ramos', 'fyden', 'fyden@gmail.com', 'c56d58f3249df20dc7ac80f33f474a89', 'customer', 'active', '2026-06-25 20:25:27'),
(6, 'Alliana Diokno', 'Alliana', 'alliana@gmail.com', 'e3060f60499a74d0b50098cdc5fed050', 'customer', 'active', '2026-08-06 17:48:24'),
(7, 'Sherly Diokno', 'Sherly', 'sherly@gmail.com', 'd993ce8bd6e8dbdc152f3283dee6a472', 'customer', 'active', '2026-08-19 10:09:16');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
