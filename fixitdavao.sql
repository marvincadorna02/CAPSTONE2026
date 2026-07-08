-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 08, 2026 at 01:49 PM
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
-- Database: `fixitdavao`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT '',
  `customer_name` varchar(255) NOT NULL,
  `customer_contact` varchar(50) NOT NULL,
  `device_type` varchar(100) DEFAULT '',
  `device_brand` varchar(150) DEFAULT '',
  `problem_description` text DEFAULT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `reply` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `shop_id`, `customer_id`, `service_id`, `service_name`, `customer_name`, `customer_contact`, `device_type`, `device_brand`, `problem_description`, `booking_date`, `booking_time`, `status`, `notes`, `created_at`, `updated_at`, `reply`) VALUES
(2, 13, 10, 17, 'Deep Cleaning PC', 'Marvin Cadorna', '09194727206', 'Desktop PC', 'Ryzen 5 5600g', 'Deep clean', '2026-03-18', '22:30:00', 'completed', NULL, '2026-03-18 02:30:48', '2026-03-18 04:23:24', NULL),
(3, 13, 14, 17, 'Deep Cleaning PC', 'Marvin Cadorna', '09194727206', 'Desktop PC', 'Ryzen 5 5600g', 'Deep Clean', '2026-03-18', '15:32:00', 'completed', NULL, '2026-03-18 05:32:20', '2026-03-18 05:33:08', NULL),
(4, 12, 14, 16, 'Tablet Speaker Problem', 'Marvin Cadorna', '09194727206', 'Tablet', 'Samsung', 'Problem Speaker', '2026-03-18', '15:48:00', 'completed', NULL, '2026-03-18 05:46:55', '2026-03-18 05:47:32', NULL),
(5, 11, 14, 15, 'Reformat', 'Marvin Cadorna', '09194727206', 'Smartphone', 'Samsung', 'Reformat', '2026-03-18', '15:54:00', 'completed', NULL, '2026-03-18 05:54:23', '2026-03-18 05:54:44', NULL),
(15, 13, 10, 17, 'Deep Cleaning PC', 'Marvin Cadorna', '09194727206', 'Tablet', 'Samsung', 'clean pc', '2026-04-15', '15:03:00', 'cancelled', ' [Rescheduled by customer]', '2026-04-13 14:01:16', '2026-04-13 14:20:53', NULL),
(16, 19, 10, 48, 'Screen Replacement', 'Marvin Cadorna', '09194727206', 'Smartphone', 'ASUS', 'Need Replacement of the Screen', '2026-05-18', '16:30:00', 'cancelled', ' [Rescheduled by customer]', '2026-05-02 04:52:25', '2026-05-03 03:05:30', NULL),
(17, 21, 22, NULL, '', 'Badarrowow', '123456789', 'Smartphone', 'IPHONE 17', 'WOW', '2026-05-25', '10:07:00', 'pending', NULL, '2026-05-21 02:06:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `shop_id`, `created_at`) VALUES
(5, 14, 12, '2026-03-18 05:47:59'),
(12, 10, 13, '2026-04-13 14:07:23');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`) VALUES
(9, 'tester@gmail.com', '2001:fd8:f291:f56d:30ee:913f:ba1a:5d9', '2026-05-24 19:37:15'),
(10, 'tester@gmail.com', '2001:fd8:f291:f56d:30ee:913f:ba1a:5d9', '2026-05-24 19:37:19'),
(11, 'tester@gmail.com', '2001:fd8:f291:f56d:30ee:913f:ba1a:5d9', '2026-05-24 19:37:23'),
(12, 'tester@gmail.com', '2001:fd8:f291:f56d:30ee:913f:ba1a:5d9', '2026-05-24 19:37:27'),
(13, 'tester@gmail.com', '2001:fd8:f291:f56d:30ee:913f:ba1a:5d9', '2026-05-24 19:37:34');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `status_seen` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`id`, `user_id`, `booking_id`, `status_seen`, `created_at`) VALUES
(1, 10, 2, 'completed', '2026-03-18 04:50:07'),
(2, 14, 3, 'completed', '2026-03-18 05:46:15'),
(3, 14, 4, 'completed', '2026-03-18 05:47:51'),
(7, 14, 5, 'completed', '2026-03-18 05:54:57'),
(320, 10, 15, 'cancelled', '2026-04-13 14:21:29'),
(322, 10, 16, 'cancelled', '2026-05-03 03:05:36');

-- --------------------------------------------------------

--
-- Table structure for table `operating_hours`
--

CREATE TABLE `operating_hours` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `day` varchar(20) NOT NULL,
  `open_time` time NOT NULL,
  `close_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `operating_hours`
--

INSERT INTO `operating_hours` (`id`, `user_id`, `day`, `open_time`, `close_time`) VALUES
(68, 13, 'monday', '09:00:00', '18:00:00'),
(69, 13, 'tuesday', '09:00:00', '18:00:00'),
(70, 13, 'wednesday', '09:00:00', '18:00:00'),
(71, 13, 'thursday', '09:00:00', '18:00:00'),
(72, 13, 'friday', '09:00:00', '18:00:00'),
(104, 11, 'monday', '09:00:00', '18:00:00'),
(105, 11, 'wednesday', '09:00:00', '18:00:00'),
(106, 11, 'friday', '09:00:00', '18:00:00'),
(141, 12, 'monday', '09:00:00', '18:00:00'),
(142, 12, 'tuesday', '09:00:00', '18:00:00'),
(143, 12, 'wednesday', '09:00:00', '18:00:00'),
(144, 12, 'thursday', '09:00:00', '18:00:00'),
(145, 12, 'friday', '09:00:00', '18:00:00'),
(146, 19, 'monday', '09:00:00', '18:00:00'),
(147, 19, 'tuesday', '09:00:00', '18:00:00'),
(148, 19, 'thursday', '09:00:00', '18:00:00'),
(149, 19, 'friday', '09:00:00', '18:00:00'),
(150, 20, 'monday', '09:00:00', '18:00:00'),
(151, 20, 'tuesday', '09:00:00', '18:00:00'),
(152, 20, 'wednesday', '09:00:00', '18:00:00'),
(153, 20, 'thursday', '09:00:00', '18:00:00'),
(154, 20, 'friday', '09:00:00', '18:00:00'),
(155, 20, 'saturday', '10:00:00', '15:00:00'),
(156, 21, 'monday', '09:00:00', '18:00:00'),
(157, 21, 'tuesday', '09:00:00', '18:00:00'),
(158, 21, 'wednesday', '09:00:00', '18:00:00'),
(159, 21, 'thursday', '09:00:00', '18:00:00'),
(160, 21, 'friday', '09:00:00', '18:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `reschedule_notifications`
--

CREATE TABLE `reschedule_notifications` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `old_date` date NOT NULL,
  `old_time` time NOT NULL,
  `new_date` date NOT NULL,
  `new_time` time NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reschedule_notifications`
--

INSERT INTO `reschedule_notifications` (`id`, `shop_id`, `booking_id`, `customer_name`, `service_name`, `old_date`, `old_time`, `new_date`, `new_time`, `is_read`, `created_at`) VALUES
(1, 16, 9, 'Marvin Cadorna', 'Reformat', '2026-04-06', '03:20:00', '2026-04-01', '15:30:00', 1, '2026-03-29 02:28:09'),
(2, 16, 9, 'Marvin Cadorna', 'Reformat', '2026-04-01', '15:30:00', '2026-03-31', '15:30:00', 1, '2026-03-29 02:28:34'),
(3, 16, 9, 'Marvin Cadorna', 'Reformat', '2026-03-31', '15:30:00', '2026-04-01', '15:30:00', 1, '2026-03-29 02:31:02'),
(4, 16, 9, 'Marvin Cadorna', 'Reformat', '2026-04-01', '15:30:00', '2026-04-01', '03:31:00', 1, '2026-03-29 02:31:38'),
(5, 16, 9, 'Marvin Cadorna', 'Reformat', '2026-04-01', '03:31:00', '2026-04-01', '15:35:00', 1, '2026-03-29 02:33:36'),
(6, 16, 9, 'Marvin Cadorna', 'Reformat', '2026-04-01', '15:35:00', '2026-03-30', '15:35:00', 1, '2026-03-29 02:34:26'),
(7, 15, 10, 'Marvin Cadorna', 'Battery Replacement', '2026-03-30', '15:40:00', '2026-04-03', '15:50:00', 1, '2026-03-29 02:49:00'),
(8, 17, 11, 'Marvin Cadorna', 'Reformat', '2026-04-03', '15:00:00', '2026-04-02', '04:00:00', 1, '2026-03-29 02:55:34'),
(9, 17, 11, 'Marvin Cadorna', 'Reformat', '2026-04-02', '04:00:00', '2026-04-10', '15:02:00', 1, '2026-03-29 02:59:27'),
(10, 17, 11, 'Marvin Cadorna', 'Reformat', '2026-04-10', '15:02:00', '2026-04-03', '03:01:00', 1, '2026-03-29 03:01:20'),
(11, 17, 11, 'Marvin Cadorna', 'Reformat', '2026-04-03', '03:01:00', '2026-04-10', '15:02:00', 1, '2026-03-29 03:02:11'),
(12, 17, 11, 'Marvin Cadorna', 'Reformat', '2026-04-10', '15:02:00', '2026-04-03', '15:10:00', 1, '2026-03-29 03:06:38'),
(13, 17, 11, 'Marvin Cadorna', 'Reformat', '2026-04-03', '15:10:00', '2026-04-10', '15:13:00', 1, '2026-03-29 03:10:57'),
(14, 16, 12, 'Marvin Cadorna', 'Reformat', '2026-05-08', '10:48:00', '2026-04-10', '00:48:00', 0, '2026-03-29 10:47:23'),
(15, 16, 12, 'Marvin Cadorna', 'Reformat', '2026-04-10', '00:48:00', '2026-04-10', '10:56:00', 0, '2026-03-29 10:52:42'),
(16, 17, 13, 'Marvin Cadorna', 'Reformat', '2026-04-10', '10:56:00', '2026-04-10', '00:55:00', 0, '2026-03-29 10:54:48'),
(17, 18, 14, 'Marvin Cadorna', 'Factory Reset', '2026-04-08', '10:40:00', '2026-04-15', '15:50:00', 1, '2026-03-29 11:38:37'),
(18, 13, 15, 'Marvin Cadorna', 'Deep Cleaning PC', '2026-04-21', '15:04:00', '2026-04-15', '15:03:00', 0, '2026-04-13 14:03:28'),
(19, 19, 16, 'Marvin Cadorna', 'Screen Replacement', '2026-05-12', '15:55:00', '2026-05-18', '16:30:00', 0, '2026-05-02 04:56:18');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `reply` text DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `booking_id`, `shop_id`, `customer_id`, `rating`, `comment`, `reply`, `replied_at`, `created_at`) VALUES
(2, 2, 13, 10, 5, 'YOU\'RE THE GOAT', 'YOU\'RE THE GOAT TOO BRO', '2026-03-18 12:32:16', '2026-03-18 04:24:50'),
(3, 4, 12, 14, 5, 'YOU\'RE THE GOAT', 'GGS', '2026-03-18 13:48:59', '2026-03-18 05:48:24'),
(4, 3, 13, 14, 5, 'TY', NULL, NULL, '2026-03-18 05:49:33'),
(5, 5, 11, 14, 5, 'THANKS BRO', NULL, NULL, '2026-03-18 05:55:10');

-- --------------------------------------------------------

--
-- Table structure for table `review_reply_reads`
--

CREATE TABLE `review_reply_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `review_reply_reads`
--

INSERT INTO `review_reply_reads` (`id`, `user_id`, `review_id`, `created_at`) VALUES
(1, 10, 2, '2026-03-18 04:50:07'),
(2, 14, 3, '2026-03-18 05:49:19');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `service_duration` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `user_id`, `service_name`, `service_fee`, `created_at`, `service_duration`) VALUES
(17, 13, 'Deep Cleaning PC', 1500.00, '2026-03-13 13:23:06', '1hr & 30 minutes'),
(34, 11, 'Reformat', 1500.00, '2026-03-29 11:16:14', '1hr & 30 minutes'),
(47, 12, 'Tablet Speaker Problem', 300.00, '2026-05-02 04:19:11', '1hr & 30 minutes'),
(48, 19, 'Screen Replacement', 1200.00, '2026-05-02 04:50:04', '1hr & 30 minutes');

-- --------------------------------------------------------

--
-- Table structure for table `shop_notification_reads`
--

CREATE TABLE `shop_notification_reads` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `status_seen` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_notification_reads`
--

INSERT INTO `shop_notification_reads` (`id`, `shop_id`, `booking_id`, `status_seen`, `created_at`) VALUES
(1, 13, 2, 'completed', '2026-03-18 05:41:51'),
(2, 13, 3, 'completed', '2026-03-18 05:41:51'),
(4, 12, 4, 'completed', '2026-03-18 05:47:36'),
(6, 11, 5, 'completed', '2026-03-18 05:54:46'),
(141, 13, 15, 'pending', '2026-04-13 14:01:38'),
(142, 19, 16, 'pending', '2026-05-02 04:52:30');

-- --------------------------------------------------------

--
-- Table structure for table `shop_review_reads`
--

CREATE TABLE `shop_review_reads` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_review_reads`
--

INSERT INTO `shop_review_reads` (`id`, `shop_id`, `review_id`, `created_at`) VALUES
(1, 13, 2, '2026-03-18 04:27:15'),
(3, 12, 3, '2026-03-18 05:49:04'),
(4, 11, 5, '2026-03-18 05:55:26'),
(89, 13, 4, '2026-04-13 14:01:38');

-- --------------------------------------------------------

--
-- Table structure for table `shop_subscriptions`
--

CREATE TABLE `shop_subscriptions` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','active','expired','rejected') NOT NULL DEFAULT 'pending',
  `payment_ref` varchar(255) DEFAULT NULL,
  `gcash_screenshot` varchar(255) DEFAULT NULL,
  `gcash_number` varchar(50) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_subscriptions`
--

INSERT INTO `shop_subscriptions` (`id`, `shop_id`, `plan_id`, `start_date`, `end_date`, `status`, `payment_ref`, `gcash_screenshot`, `gcash_number`, `admin_note`, `created_at`, `updated_at`) VALUES
(2, 12, 1, '2026-05-24', '2026-06-23', 'expired', '23232323232', 'uploads/gcash/gcash_12_1779621199.jpg', '8978979', '', '2026-05-24 11:13:19', '2026-07-08 11:15:43');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` int(11) NOT NULL DEFAULT 30,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `price`, `duration_days`, `description`, `is_active`, `created_at`) VALUES
(1, 'Monthly', 299.00, 30, 'Monthly access to Fix It Davao platform', 1, '2026-05-21 01:49:23'),
(2, 'Quarterly', 799.00, 90, '3-month access — save ₱98 vs monthly', 1, '2026-05-21 01:49:23'),
(3, 'Annual', 2999.00, 365, '12-month access — best value, save ₱589', 1, '2026-05-21 01:49:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','repairshop','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `suspend_reason` varchar(255) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `shop_name` varchar(255) DEFAULT NULL,
  `shop_location` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `profile_picture` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `status`, `suspend_reason`, `approval_status`, `rejection_reason`, `approved_at`, `rejected_at`, `logo_url`, `shop_name`, `shop_location`, `contact_number`, `latitude`, `longitude`, `profile_picture`) VALUES
(1, 'Admin', 'admin@fixitdavao.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRpKiyDA1', 'admin', '2026-03-12 09:08:01', 'active', NULL, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'Marvin Cadorna', 'marvincadorna07@gmail.com', '$2y$10$ZBRY1nG4DEp6LPvkKFE2p.MiloAf57k03zcqYWoogSAyCK.4sA/iG', 'customer', '2026-03-13 13:13:28', 'active', NULL, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAfQAAAH0CAIAAABEtEjdAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAFs2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSfvu78nIGlkPSdXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQnPz4KPHg6eG1wbWV0YSB4bWxuczp4PSdhZG9iZTpuczptZXRhLyc+CjxyZGY6UkRGIHhtbG5zOnJkZj0naHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyc+CgogPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9JycKICB4bWxuczpBdHRyaWI9J2h0dHA6Ly9ucy5hdHRyaWJ1dGlvbi5jb20vYWRzLzEuMC8nPgogIDxBdHRyaWI6QWRzPgogICA8cmRmOlNlcT4KICAgIDxyZGY6bGkgcmRmOnBhcnNlVHlwZT0nUmVzb3VyY2UnPgogICAgIDxBdHRyaWI6Q3JlYXRlZD4yMDI2LTAxLTMwPC9BdHRyaWI6Q3JlYXRlZD4KICAgICA8QXR0cmliOkRhdGE+eyZxdW90O2RvYyZxdW90OzomcXVvdDtEQUdfNDlkZlVDYyZxdW90OywmcXVvdDt1c2VyJnF1b3Q7OiZxdW90O1VBR180Nnh2RXN3JnF1b3Q7LCZxdW90O2JyYW5kJnF1b3Q7OiZxdW90O0JBR180N0dJakJvJnF1b3Q7LCZxdW90O3RlbXBsYXRlJnF1b3Q7OiZxdW90O0NvbG9yZnVsIE1vYmlsZSBTZXJ2aWNlIEZyZWUgTG9nbyZxdW90O308L0F0dHJpYjpEYXRhPgogICAgIDxBdHRyaWI6RXh0SWQ+NmZkMjc2MDQtZGU4ZS00ZGJmLWFhZGYtYjMwNzBiYmI0ZGJhPC9BdHRyaWI6RXh0SWQ+CiAgICAgPEF0dHJpYjpGYklkPjUyNTI2NTkxNDE3OTU4MDwvQXR0cmliOkZiSWQ+CiAgICAgPEF0dHJpYjpUb3VjaFR5cGU+MjwvQXR0cmliOlRvdWNoVHlwZT4KICAgIDwvcmRmOmxpPgogICA8L3JkZjpTZXE+CiAgPC9BdHRyaWI6QWRzPgogPC9yZGY6RGVzY3JpcHRpb24+CgogPHJkZjpE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'),
(11, 'SUKUNA', 'cadorna1@gmail.com', '$2y$10$kEahmMK1SG31kEMTQXmvy./JB5tUikHuLWHGbTayHxNtie0ZYMu4G', 'repairshop', '2026-03-13 13:17:06', 'active', NULL, 'approved', NULL, '2026-03-13 21:17:12', NULL, '../uploads/shop-logos/shop_11_1773407948.jpg', 'SUKUNA', 'Matina Aplaya Davao City', '09194727206', NULL, NULL, NULL),
(12, 'MARVIN & ADAN SHOP', 'cadorna2@gmail.com', '$2y$10$wZCEpGR1lRhUpkH4ccGcr.KqodPHzXM/dfL77EQHh5QugMdB56to.', 'repairshop', '2026-03-13 13:17:32', 'active', NULL, 'approved', NULL, '2026-03-13 21:17:44', NULL, '../uploads/shop-logos/shop_12_1776690945.jpg', 'MARVIN & ADAN SHOP', 'Matina Aplaya Davao City', '09194727206', 7.04447020, 125.56872970, NULL),
(13, 'YUJI SHOP', 'cadorna3@gmail.com', '$2y$10$KAG8wWCmPA3DjIbLrXf0Suyv2TgY719J2i9GB9..XVcHTdpA3C7La', 'repairshop', '2026-03-13 13:18:06', 'active', NULL, 'approved', NULL, '2026-03-13 21:18:11', NULL, '../uploads/shop-logos/shop_13_1773408097.jpg', 'YUJI SHOP', 'Sunrise Village Matina Davao City', '09194727206', NULL, NULL, NULL),
(14, 'Marvin Cadorna', 'cadorna0@gmail.com', '$2y$10$9tEtUS1.jiznDxRtrQt3feoOgYUKccu9tA7r9yiBx2Qi0Z8f4uz.C', 'customer', '2026-03-18 05:31:40', 'active', NULL, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'JORGE SHOP', 'jrcadorna11@gmail.com', '$2y$10$fTsnlrWbsR6M2Dxaj.P5HelJD4nRYcoIVKTTnS6pxc4RbT2700s0G', 'repairshop', '2026-04-13 14:10:52', 'active', NULL, 'approved', NULL, '2026-04-13 22:20:31', NULL, '../uploads/shop-logos/shop_19_1776513841.png', 'JORGE SHOP', 'Kawayan Drive', '09194727206', 7.05085540, 125.57022920, NULL),
(20, 'John', 'johncarlo_amila@sjp2cd.edu.ph', '$2y$10$s2b4mDjC1rCrty8ya3jn0OcmRL4Og3T.o3yGXMxtLEj.y7N0dlFYq', 'repairshop', '2026-05-13 23:10:01', 'active', NULL, 'approved', NULL, '2026-05-14 07:47:03', NULL, '../uploads/shop-logos/shop_20_1778714217.jpg', 'John', 'Alejandra homes', '565646464313', 7.06178240, 125.59298890, NULL),
(21, 'Adan\'s Shop', 'adanian10@gmail.com', '$2y$10$duuJ/MLNvgp8fY0yhaNx3uzrs91iH.rDSVexYDhA3.b65IiZgqryy', 'repairshop', '2026-05-13 23:15:40', 'active', NULL, 'approved', NULL, '2026-05-14 07:29:22', NULL, NULL, 'Adan\'s Shop', 'Prk. 2 Rosal St Mintal Davao City', '09464133489', 7.09244280, 125.49157250, NULL),
(22, 'Badarrowow', 'badarrowow@gmail.com', '$2y$10$l8hT.EcC4761kWDypl2CkOfdw3DkYU7pxXdD7PufFTYgdVrFD/cKS', 'customer', '2026-05-21 02:04:43', 'active', NULL, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'Zan', 'tester@gmail.com', '$2y$10$SUarffG6ZXL5AYj4RBd5EeT.nzaO2JPelY7/M0DS1gtk7WEzxd4X2', 'customer', '2026-05-24 11:35:24', 'active', NULL, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`shop_id`),
  ADD KEY `fk_favorites_shop` (`shop_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_notif` (`user_id`,`booking_id`,`status_seen`),
  ADD KEY `fk_nr_booking` (`booking_id`);

--
-- Indexes for table `operating_hours`
--
ALTER TABLE `operating_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_operating_hours_user` (`user_id`);

--
-- Indexes for table `reschedule_notifications`
--
ALTER TABLE `reschedule_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `fk_reviews_shop` (`shop_id`),
  ADD KEY `fk_reviews_customer` (`customer_id`);

--
-- Indexes for table `review_reply_reads`
--
ALTER TABLE `review_reply_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reply_read` (`user_id`,`review_id`),
  ADD KEY `fk_rrr_review` (`review_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shop_notification_reads`
--
ALTER TABLE `shop_notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_notif` (`shop_id`,`booking_id`,`status_seen`),
  ADD KEY `fk_snr_booking` (`booking_id`);

--
-- Indexes for table `shop_review_reads`
--
ALTER TABLE `shop_review_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review_notif` (`shop_id`,`review_id`),
  ADD KEY `fk_srr_review` (`review_id`);

--
-- Indexes for table `shop_subscriptions`
--
ALTER TABLE `shop_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=332;

--
-- AUTO_INCREMENT for table `operating_hours`
--
ALTER TABLE `operating_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `reschedule_notifications`
--
ALTER TABLE `reschedule_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `review_reply_reads`
--
ALTER TABLE `review_reply_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=202;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `shop_notification_reads`
--
ALTER TABLE `shop_notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT for table `shop_review_reads`
--
ALTER TABLE `shop_review_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `shop_subscriptions`
--
ALTER TABLE `shop_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_favorites_shop` FOREIGN KEY (`shop_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD CONSTRAINT `fk_nr_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `operating_hours`
--
ALTER TABLE `operating_hours`
  ADD CONSTRAINT `fk_operating_hours_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `operating_hours_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_shop` FOREIGN KEY (`shop_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `review_reply_reads`
--
ALTER TABLE `review_reply_reads`
  ADD CONSTRAINT `fk_rrr_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rrr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_notification_reads`
--
ALTER TABLE `shop_notification_reads`
  ADD CONSTRAINT `fk_snr_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_snr_shop` FOREIGN KEY (`shop_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_review_reads`
--
ALTER TABLE `shop_review_reads`
  ADD CONSTRAINT `fk_srr_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_srr_shop` FOREIGN KEY (`shop_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_subscriptions`
--
ALTER TABLE `shop_subscriptions`
  ADD CONSTRAINT `shop_subscriptions_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
