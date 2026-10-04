-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2026 at 09:55 AM
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
-- Database: `business_community`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_messages`
--

CREATE TABLE `admin_messages` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `business_user_id` int(11) NOT NULL,
  `sender_role` enum('admin','business') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by_admin` tinyint(1) DEFAULT 0,
  `deleted_by_business` tinyint(1) DEFAULT 0,
  `deleted_for_everyone` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_messages`
--

INSERT INTO `admin_messages` (`id`, `admin_id`, `business_user_id`, `sender_role`, `message`, `created_at`, `deleted_by_admin`, `deleted_by_business`, `deleted_for_everyone`, `is_read`, `read_at`) VALUES
(1, 1, 2, 'business', 'Hello Admin', '2026-02-03 07:30:17', 0, 0, 0, 0, NULL),
(2, 1, 2, 'admin', 'Yes... Good Afternoon', '2026-02-03 07:37:06', 0, 0, 0, 0, NULL),
(3, 1, 11, 'business', 'Hello Admin', '2026-02-03 07:54:29', 0, 0, 0, 0, NULL),
(4, 1, 11, 'business', 'This is Jeeva from Supermarket', '2026-02-03 07:54:49', 0, 0, 0, 0, NULL),
(5, 1, 2, 'business', 'hai', '2026-02-11 07:26:20', 0, 0, 0, 0, NULL),
(6, 1, 2, 'business', 'Hai', '2026-02-11 07:30:55', 0, 0, 0, 0, NULL),
(7, 1, 2, 'business', 'Hello admin', '2026-02-11 07:31:54', 0, 0, 0, 0, NULL),
(8, 1, 2, 'admin', 'hai', '2026-02-11 07:32:14', 0, 0, 0, 0, NULL),
(9, 1, 2, 'business', 'hi! may i know who is this?', '2026-02-11 09:19:03', 0, 0, 0, 0, NULL),
(10, 1, 2, 'business', 'i mean whom you are?', '2026-02-11 09:19:28', 0, 0, 0, 0, NULL),
(11, 1, 2, 'business', 'Hi', '2026-02-12 16:12:04', 0, 0, 0, 0, NULL),
(12, 1, 2, 'business', 'Hi', '2026-02-13 04:59:46', 0, 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_profiles`
--

CREATE TABLE `business_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(200) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `short_bio` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `sub_category_id` int(11) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `profile_completion` int(11) DEFAULT 0,
  `status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verification_status` enum('NEW','PENDING','APPROVED','REJECTED') DEFAULT 'NEW',
  `verification_reason` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_profiles`
--

INSERT INTO `business_profiles` (`id`, `user_id`, `business_name`, `logo`, `cover_image`, `short_bio`, `category_id`, `sub_category_id`, `city`, `area`, `phone`, `whatsapp`, `website`, `gst_number`, `license_number`, `is_verified`, `verified_at`, `profile_completion`, `status`, `created_at`, `updated_at`, `verification_status`, `verification_reason`, `verified_by`) VALUES
(4, 2, 'karthick\'s show room', 'uploads/logos/logo_6981a3dbbe10f.png', 'uploads/covers/cover_6981a3dbbe2aa.jpg', 'karthick’s show room offers a curated range of premium products that blend elegance, performance, and reliability — because you deserve the best.', 6, 35, 'karaikudi', 'chennai, karaikudi, erode', '8428471708', '8428471708', 'https://qads.co.in', NULL, NULL, 0, NULL, 100, 'ACTIVE', '2026-02-03 07:29:31', '2026-02-03 07:29:31', 'PENDING', NULL, NULL),
(5, 11, 'jeeva\'s store', 'uploads/logos/logo_6981a789f17dd.jpg', 'uploads/covers/cover_6981a789f1a5c.jpg', 'jeeva vegetables shop is your trusted neighborhood store for fresh greens, quality produce, and natural goodness in every bite.', 1, 1, 'karaikudi', 'chennai, karaikudi, erode', '8122513669', '8122513669', 'https://nirubanguna.com', NULL, NULL, 0, NULL, 100, 'ACTIVE', '2026-02-03 07:45:13', '2026-02-03 07:45:13', 'PENDING', NULL, NULL),
(6, 15, 'thulasi ragul garments', 'uploads/logos/logo_69c0d28f77bc1.png', NULL, 'i am thulasi ragul raj', 2, 12, 'karaikudi', 'karaikudi', '8825920268', '8825920268', NULL, '33ABCDE1234F1Z5', 'LIC-2026-001', 1, '2026-03-26 12:17:27', 100, 'ACTIVE', '2026-03-23 05:41:35', '2026-03-26 06:47:27', 'APPROVED', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `icon`, `status`, `created_at`) VALUES
(1, 'Restaurants', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(2, 'Hotels', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(3, 'Beauty & Spa', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(4, 'Home Decor', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(5, 'Wedding Planning', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(6, 'Education', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(7, 'Rent & Hire', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(8, 'Hospitals', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(9, 'Contractors', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(10, 'Pet Shops', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(11, 'PG / Hostels', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(12, 'Estate Agent / Real Estate', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(13, 'Dentists', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(14, 'Gym', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(15, 'Loans', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(16, 'Event Organisers', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(17, 'Driving Schools', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(18, 'Packers & Movers', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(19, 'Courier Services', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(20, 'AC Service', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(21, 'Astrologers', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(22, 'Body Massage Centers', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(23, 'Caterers', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(24, 'Chartered Accountant', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(25, 'Computer Training Institutes', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(26, 'Computer & Laptop Repair', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(27, 'Car Repair & Services', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(28, 'Dermatologists', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(29, 'Electricians', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(30, 'Event Organizer (Small / Freelance)', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(31, 'Real Estate (Developer Side)', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(32, 'Fabricators', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(33, 'Furniture Repair Services', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(34, 'House Keeping Services', NULL, 'ACTIVE', '2026-02-03 07:22:58'),
(35, 'Hobbies', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(36, 'Interior Designers', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(37, 'Internet / Website Designers', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(38, 'Jewellery Showrooms', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(39, 'Lawyers', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(40, 'Transporters', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(41, 'Photographers', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(42, 'Nursing Services', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(43, 'Printing & Publishing Services', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(44, 'Placement Services', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(45, 'Pest Control Services', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(46, 'Painting Contractors', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(47, 'Scrap Dealers / Buyers', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(48, 'Security System', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(49, 'Coaching', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(50, 'Vocational Training', NULL, 'ACTIVE', '2026-02-03 07:22:59'),
(51, 'Registration Consultants', NULL, 'ACTIVE', '2026-02-03 07:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `id` int(11) NOT NULL,
  `user_one` int(11) NOT NULL,
  `user_two` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by_one` tinyint(1) DEFAULT 0,
  `deleted_by_two` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chats`
--

INSERT INTO `chats` (`id`, `user_one`, `user_two`, `created_by`, `created_at`, `deleted_by_one`, `deleted_by_two`) VALUES
(1, 2, 1, 2, '2026-02-03 07:30:10', 0, 0),
(2, 11, 1, 11, '2026-02-03 07:54:24', 0, 0),
(3, 2, 11, 2, '2026-02-03 09:33:18', 0, 0),
(4, 15, 11, 15, '2026-03-23 05:43:14', 0, 0),
(5, 15, 2, 15, '2026-03-23 05:43:50', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `chat_activity_log`
--

CREATE TABLE `chat_activity_log` (
  `id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `business_one` int(11) NOT NULL,
  `business_two` int(11) NOT NULL,
  `message_count` int(11) DEFAULT 0,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_activity_log`
--

INSERT INTO `chat_activity_log` (`id`, `chat_id`, `business_one`, `business_two`, `message_count`, `last_message_at`, `created_at`) VALUES
(1, 1, 2, 1, 2, '2026-02-11 10:37:31', '2026-02-03 07:30:10'),
(2, 2, 11, 1, 0, NULL, '2026-02-03 07:54:24'),
(3, 3, 2, 11, 2, '2026-02-11 09:25:25', '2026-02-03 09:33:18'),
(4, 4, 15, 11, 0, NULL, '2026-03-23 05:43:14'),
(5, 5, 15, 2, 1, '2026-03-23 05:57:28', '2026-03-23 05:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `message_type` enum('TEXT','IMAGE','FILE') DEFAULT 'TEXT',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `deleted_for_everyone` tinyint(1) DEFAULT 0,
  `deleted_by_sender` tinyint(1) DEFAULT 0,
  `deleted_by_receiver` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `chat_id`, `sender_id`, `message`, `file_url`, `file_name`, `file_size`, `message_type`, `created_at`, `is_read`, `read_at`, `deleted_for_everyone`, `deleted_by_sender`, `deleted_by_receiver`) VALUES
(1, 3, 2, 'hello sir', NULL, NULL, NULL, 'TEXT', '2026-02-03 09:33:23', 0, NULL, 0, 0, 0),
(2, 1, 2, 'hi', NULL, NULL, NULL, 'TEXT', '2026-02-11 09:13:56', 0, NULL, 0, 0, 0),
(3, 3, 2, 'hello', NULL, NULL, NULL, 'TEXT', '2026-02-11 09:25:25', 0, NULL, 0, 0, 0),
(4, 1, 2, 'hi', NULL, NULL, NULL, 'TEXT', '2026-02-11 10:37:31', 0, NULL, 0, 0, 0),
(5, 5, 2, 'hi bro', NULL, NULL, NULL, 'TEXT', '2026-03-23 05:57:28', 0, NULL, 0, 0, 0),
(6, 5, 15, 'hiiiii', NULL, NULL, NULL, 'TEXT', '2026-03-26 05:48:32', 0, NULL, 0, 0, 0),
(7, 5, 15, 'hiiiii', NULL, NULL, NULL, 'TEXT', '2026-03-26 05:49:28', 0, NULL, 0, 0, 0),
(8, 5, 15, NULL, 'uploads/chat/69c4c9a23a0e1.pdf', 'quotation_QADS20261001 (2).pdf', 88257, 'FILE', '2026-03-26 05:52:34', 0, NULL, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `chat_typing`
--

CREATE TABLE `chat_typing` (
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communities`
--

CREATE TABLE `communities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `type` enum('BUSINESS','EVENT') DEFAULT 'BUSINESS',
  `status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communities`
--

INSERT INTO `communities` (`id`, `name`, `description`, `created_by`, `type`, `status`, `created_at`) VALUES
(1, 'Marriage Event Group', 'All vendors for this event', 15, 'EVENT', 'ACTIVE', '2026-03-24 06:42:48'),
(2, 'Updated Business Hub', 'New description', 18, 'BUSINESS', 'INACTIVE', '2026-03-25 09:10:07');

-- --------------------------------------------------------

--
-- Table structure for table `community_invites`
--

CREATE TABLE `community_invites` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `invited_user_id` int(11) NOT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_invites`
--

INSERT INTO `community_invites` (`id`, `community_id`, `invited_by`, `invited_user_id`, `status`, `created_at`, `responded_at`) VALUES
(1, 1, 15, 16, 'ACCEPTED', '2026-03-24 07:25:16', '2026-03-24 13:00:01'),
(2, 1, 15, 17, 'ACCEPTED', '2026-03-24 07:35:26', '2026-03-24 13:06:11'),
(3, 2, 18, 15, 'PENDING', '2026-03-25 09:11:49', NULL),
(4, 1, 15, 17, 'ACCEPTED', '2026-03-26 04:49:53', '2026-03-26 10:42:27'),
(5, 1, 15, 16, 'PENDING', '2026-03-26 04:51:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `community_members`
--

CREATE TABLE `community_members` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('ADMIN','MEMBER') DEFAULT 'MEMBER',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_members`
--

INSERT INTO `community_members` (`id`, `community_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 1, 15, 'ADMIN', '2026-03-24 06:42:48'),
(4, 2, 18, 'ADMIN', '2026-03-25 09:10:07'),
(5, 1, 17, 'MEMBER', '2026-03-26 05:12:27');

-- --------------------------------------------------------

--
-- Table structure for table `community_messages`
--

CREATE TABLE `community_messages` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `message_type` enum('TEXT','IMAGE') DEFAULT 'TEXT',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_messages`
--

INSERT INTO `community_messages` (`id`, `community_id`, `sender_id`, `message`, `message_type`, `created_at`) VALUES
(1, 1, 15, 'Hello team 👋', 'TEXT', '2026-03-24 07:39:16'),
(2, 1, 16, 'Hello sir 👋', 'TEXT', '2026-03-24 07:40:25'),
(3, 1, 16, 'uploads/chat/breadcrumb1.jpg', 'IMAGE', '2026-03-24 07:43:40'),
(4, 1, 16, 'i\'m ragul sir 👋', 'TEXT', '2026-03-24 07:46:57'),
(5, 1, 16, 'uploads/community_chat/img_1774338482_6175.jpg', 'IMAGE', '2026-03-24 07:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `status` enum('PLANNING','ONGOING','COMPLETED') DEFAULT 'PLANNING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `client_id`, `title`, `description`, `event_date`, `status`, `created_at`, `completed_at`) VALUES
(1, 18, 'Updated Marriage Event', 'My sister wedding event', '2026-03-24', 'COMPLETED', '2026-03-24 08:16:39', NULL),
(2, 16, 'cultural Function', 'My sister wedding event', '2026-06-15', 'ONGOING', '2026-03-26 04:56:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_communities`
--

CREATE TABLE `event_communities` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_communities`
--

INSERT INTO `event_communities` (`id`, `event_id`, `community_id`) VALUES
(2, 1, 1),
(3, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `event_vendors`
--

CREATE TABLE `event_vendors` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `service_type` varchar(100) DEFAULT NULL,
  `status` enum('INVITED','ACCEPTED','REJECTED','COMPLETED') DEFAULT 'INVITED',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_vendors`
--

INSERT INTO `event_vendors` (`id`, `event_id`, `vendor_id`, `service_type`, `status`, `joined_at`) VALUES
(2, 1, 15, 'Hotels', 'ACCEPTED', '2026-03-24 10:11:34'),
(3, 1, 16, 'Photography', 'ACCEPTED', '2026-03-24 10:13:28'),
(4, 2, 15, 'Hotels', 'ACCEPTED', '2026-03-26 04:57:22'),
(5, 2, 17, 'Photography', 'INVITED', '2026-03-26 05:14:32');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `actor_user_id`, `type`, `reference_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-03 07:30:17'),
(2, 2, 1, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-03 07:37:06'),
(3, 11, 11, 'admin_message', NULL, 'Admin sent you a message', 0, '2026-02-03 07:54:29'),
(4, 11, 11, 'admin_message', NULL, 'Admin sent you a message', 0, '2026-02-03 07:54:49'),
(5, 2, 11, 'post_like', 1, 'liked your post', 1, '2026-02-03 09:30:17'),
(6, 2, 11, 'post_comment', 1, 'commented on your post', 1, '2026-02-03 09:30:31'),
(7, 11, 2, 'chat_message', 3, 'sent you a message', 0, '2026-02-03 09:33:23'),
(8, 11, 2, 'post_comment', 2, 'commented on your post', 1, '2026-02-03 10:15:49'),
(9, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-11 07:26:20'),
(10, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-11 07:30:55'),
(11, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-11 07:31:54'),
(12, 2, 1, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-11 07:32:14'),
(13, 2, 1, 'admin_announcement', NULL, 'admin portal', 1, '2026-02-11 07:33:38'),
(14, 11, 1, 'admin_announcement', NULL, 'admin portal', 0, '2026-02-11 07:33:38'),
(15, 1, 2, 'chat_message', 1, 'sent you a message', 0, '2026-02-11 09:13:56'),
(16, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-11 09:19:03'),
(17, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-11 09:19:28'),
(18, 11, 2, 'chat_message', 3, 'sent you a message', 0, '2026-02-11 09:25:25'),
(19, 1, 2, 'chat_message', 1, 'sent you a message', 0, '2026-02-11 10:37:31'),
(20, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-12 16:12:04'),
(21, 2, 2, 'admin_message', NULL, 'Admin sent you a message', 1, '2026-02-13 04:59:46'),
(22, 2, 1, 'admin_announcement', NULL, 'Hello All... \nHappy Pongal to All', 1, '2026-02-13 05:07:25'),
(23, 11, 1, 'admin_announcement', NULL, 'Hello All... \nHappy Pongal to All', 0, '2026-02-13 05:07:25'),
(24, 15, 2, 'chat_message', 5, 'sent you a message', 1, '2026-03-23 05:57:28'),
(25, 15, 16, 'community_invite_response', 1, 'User accepted your community invite', 0, '2026-03-24 07:30:01'),
(26, 15, 17, 'community_invite_response', 2, 'User accepted your community invite', 0, '2026-03-24 07:36:11'),
(27, 16, 15, 'event_invite', 1, 'You have been invited to an event', 0, '2026-03-24 09:03:38'),
(28, 16, 15, 'event_invite', 3, 'You have been invited to an event', 0, '2026-03-24 10:13:28'),
(29, 18, 16, 'event_vendor_response', 3, 'Vendor accepted your event request', 0, '2026-03-24 10:26:39'),
(30, 18, 15, 'DEAL_INVITE', 3, 'thulasi garments sent you a deal request', 0, '2026-03-25 08:51:14'),
(31, 15, 18, 'DEAL_RESPONSE', 3, 'manoj supermarkets accepted your deal request', 0, '2026-03-25 09:24:14'),
(32, 18, 15, 'DEAL_MESSAGE', 3, 'thulasi garments sent you a message', 0, '2026-03-25 09:24:26'),
(33, 18, 15, 'DEAL_MESSAGE', 3, 'thulasi garments sent you a message', 0, '2026-03-25 09:33:30'),
(34, 15, 18, 'DEAL_MESSAGE', 3, 'manoj supermarkets sent you a message', 0, '2026-03-25 10:49:08'),
(35, 18, 15, 'DEAL_COMPLETED', 3, 'thulasi garments marked the deal as completed', 0, '2026-03-25 11:06:13'),
(36, 16, 15, 'COMMUNITY_REMOVED', 1, 'thulasi garments removed you from Marriage Event Group', 0, '2026-03-26 04:46:21'),
(37, 17, 15, 'COMMUNITY_INVITE', 1, 'thulasi garments invited you to join Marriage Event Group', 0, '2026-03-26 04:49:53'),
(38, 16, 15, 'COMMUNITY_INVITE', 1, 'thulasi garments invited you to join Marriage Event Group', 0, '2026-03-26 04:51:32'),
(39, 15, 16, 'EVENT_COMMUNITY_LINKED', 2, 'ragul supermarkets added your community \'Marriage Event Group\' to an event', 0, '2026-03-26 04:57:22'),
(40, 15, 17, 'community_invite_response', 4, 'User accepted your community invite', 0, '2026-03-26 05:12:27'),
(41, 17, 15, 'EVENT_VENDOR_INVITE', 2, 'thulasi garments invited you to participate in an event', 0, '2026-03-26 05:14:32'),
(42, 1, 15, 'BUSINESS_VERIFY_REQUEST', 6, 'thulasi ragul garments requested verification', 0, '2026-03-26 06:39:01'),
(43, 15, 1, 'BUSINESS_APPROVED', 6, '🎉 Your business \'thulasi ragul garments\' has been approved and verified', 0, '2026-03-26 06:47:27'),
(44, 15, 15, 'MEETING_CREATED', 1, 'Meeting \'Client Discussion\' scheduled successfully', 0, '2026-03-26 07:40:58'),
(45, 15, 15, 'MEETING_CREATED', 2, 'Meeting \'user Discussion\' scheduled successfully', 0, '2026-03-26 07:54:31'),
(46, 15, 15, 'MEETING_CREATED', 3, 'Meeting \'user Discussion\' scheduled successfully', 0, '2026-03-26 08:38:02');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `post_type` enum('TEXT','IMAGE','VIDEO') DEFAULT 'TEXT',
  `status` enum('ACTIVE','DELETED') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `content`, `post_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'suzuki என்பது ஜப்பானை சேர்ந்த உலகப் புகழ்பெற்ற வாகன உற்பத்தி நிறுவனம்.\r\nஇந்நிறுவனம் சிறிய மற்றும் எரிபொருள் சிக்கனமான கார்களை உருவாக்குவதில் முன்னிலை வகிக்கிறது.\r\nsuzuki வாகனங்கள் நம்பகத்தன்மை மற்றும் குறைந்த பராமரிப்பு செலவுக்காக அறியப்படுகின்றன.\r\nஇந்தியாவில் maruti suzuki india limited மூலம் suzuki கார்களுக்கு மிகப்பெரிய வரவேற்பு கிடைத்துள்ளது.\r\nswift, baleno, alto போன்ற மாடல்கள் மக்கள் மனதில் தனி இடம் பெற்றவை.\r\nசிறந்த மைலேஜ் மற்றும் சுலபமான ஓட்ட அனுபவம் suzuki கார்களின் முக்கிய அம்சமாகும்.\r\nநவீன பாதுகாப்பு வசதிகள் மற்றும் தொழில்நுட்ப மேம்பாடுகளுடன் புதிய மாடல்கள் வெளியிடப்படுகின்றன.\r\nநகர்ப்புற மற்றும் கிராமப்புற பயணங்களுக்கு ஏற்ற கார்களை suzuki வழங்குகிறது.\r\nகுடும்ப பயன்பாட்டிற்கு ஏற்ற வசதியான மற்றும் விலை குறைந்த தேர்வுகளை suzuki அளிக்கிறது.\r\nஉலகம் முழுவதும் நம்பிக்கையுடன் பயன்படுத்தப்படும் ஒரு முன்னணி கார் பிராண்டாக suzuki திகழ்கிறது.', 'IMAGE', 'ACTIVE', '2026-02-03 07:33:00', '2026-02-11 09:21:31'),
(2, 11, 'காய்கறிகள் மனித உடலுக்கு மிகவும் முக்கியமான சத்துக்கள் நிறைந்த இயற்கை உணவுகள் ஆகும். தினசரி உணவில் காய்கறிகளை சேர்த்துக் கொள்வது நல்ல ஆரோக்கியத்திற்கு அடிப்படை. இவை வைட்டமின்கள், தாதுக்கள், நார்ச்சத்து மற்றும் ஆன்டி-ஆக்ஸிடண்ட்கள் போன்ற பல பயனுள்ள சத்துக்களை வழங்குகின்றன. காய்கறிகள் உடலின் நோய் எதிர்ப்பு சக்தியை அதிகரித்து, பல நோய்களைத் தடுக்க உதவுகின்றன. குறிப்பாக பச்சை கீரைகள் இரத்த சோகையை குறைக்க உதவுகின்றன. கேரட், பீட்ரூட் போன்ற வண்ணமயமான காய்கறிகள் கண் பார்வைக்கும் இரத்த ஓட்டத்திற்கும் நன்மை தருகின்றன. நார்ச்சத்து அதிகம் உள்ளதால் செரிமானம் சீராக இருக்கும். கொழுப்பு குறைவாக இருப்பதால் உடல் எடையை கட்டுப்படுத்த உதவுகிறது. இயற்கையான உணவாக இருப்பதால் உடலுக்கு எளிதில் செரிமானமாகி, சக்தியையும் சுறுசுறுப்பையும் அதிகரிக்கிறது. ஆரோக்கியமான வாழ்க்கைக்கு தினமும் பலவித காய்கறிகளை உணவில் சேர்ப்பது மிகவும் அவசியம். 🌿', 'IMAGE', 'ACTIVE', '2026-02-03 07:55:56', '2026-02-03 07:55:56'),
(3, 15, 'hi', 'TEXT', 'ACTIVE', '2026-03-23 05:44:55', '2026-03-23 05:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `post_comments`
--

CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `status` enum('ACTIVE','DELETED') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_comments`
--

INSERT INTO `post_comments` (`id`, `post_id`, `user_id`, `comment`, `status`, `created_at`) VALUES
(1, 2, 11, 'nice update', 'ACTIVE', '2026-02-03 07:56:11'),
(2, 1, 11, 'wow', 'ACTIVE', '2026-02-03 09:30:31'),
(3, 2, 2, 'good one sir', 'ACTIVE', '2026-02-03 10:15:49'),
(4, 1, 2, 'nice', 'ACTIVE', '2026-02-11 09:31:36');

-- --------------------------------------------------------

--
-- Table structure for table `post_media`
--

CREATE TABLE `post_media` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `media_type` enum('IMAGE','VIDEO') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_media`
--

INSERT INTO `post_media` (`id`, `post_id`, `media_url`, `media_type`, `created_at`) VALUES
(1, 1, 'uploads/posts/post_6981a4acf0777.jpg', 'IMAGE', '2026-02-03 07:33:00'),
(2, 2, 'uploads/posts/post_6981aa0cb55e6.jpg', 'IMAGE', '2026-02-03 07:55:56');

-- --------------------------------------------------------

--
-- Table structure for table `post_reactions`
--

CREATE TABLE `post_reactions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` enum('LIKE') DEFAULT 'LIKE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_reactions`
--

INSERT INTO `post_reactions` (`id`, `post_id`, `user_id`, `reaction`, `created_at`) VALUES
(1, 2, 11, 'LIKE', '2026-02-03 09:30:14'),
(2, 1, 11, 'LIKE', '2026-02-03 09:30:17');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `deal_id` int(11) DEFAULT NULL,
  `reviewer_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `review` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `event_id`, `deal_id`, `reviewer_id`, `vendor_id`, `rating`, `review`, `created_at`, `deleted_at`) VALUES
(1, NULL, 3, 15, 18, 2, 'Very professional service', '2026-03-25 11:26:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sub_categories`
--

CREATE TABLE `sub_categories` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sub_categories`
--

INSERT INTO `sub_categories` (`id`, `category_id`, `name`, `status`, `created_at`) VALUES
(1, 1, 'South Indian', 'ACTIVE', '2026-02-03 07:22:58'),
(2, 1, 'North Indian', 'ACTIVE', '2026-02-03 07:22:58'),
(3, 1, 'Chinese', 'ACTIVE', '2026-02-03 07:22:58'),
(4, 1, 'Fast Food', 'ACTIVE', '2026-02-03 07:22:58'),
(5, 1, 'Bakery', 'ACTIVE', '2026-02-03 07:22:58'),
(6, 1, 'Cafe', 'ACTIVE', '2026-02-03 07:22:58'),
(7, 1, 'Pure Veg', 'ACTIVE', '2026-02-03 07:22:58'),
(8, 1, 'Non-Veg', 'ACTIVE', '2026-02-03 07:22:58'),
(9, 1, 'Multi-Cuisine', 'ACTIVE', '2026-02-03 07:22:58'),
(10, 1, 'Cloud Kitchen', 'ACTIVE', '2026-02-03 07:22:58'),
(11, 2, 'Budget Hotels', 'ACTIVE', '2026-02-03 07:22:58'),
(12, 2, 'Luxury Hotels', 'ACTIVE', '2026-02-03 07:22:58'),
(13, 2, 'Lodges', 'ACTIVE', '2026-02-03 07:22:58'),
(14, 2, 'Service Apartments', 'ACTIVE', '2026-02-03 07:22:58'),
(15, 2, 'Resorts', 'ACTIVE', '2026-02-03 07:22:58'),
(16, 2, 'Business Hotels', 'ACTIVE', '2026-02-03 07:22:58'),
(17, 3, 'Beauty Parlour', 'ACTIVE', '2026-02-03 07:22:58'),
(18, 3, 'Hair Salon', 'ACTIVE', '2026-02-03 07:22:58'),
(19, 3, 'Bridal Makeup', 'ACTIVE', '2026-02-03 07:22:58'),
(20, 3, 'Spa & Massage', 'ACTIVE', '2026-02-03 07:22:58'),
(21, 3, 'Skin Care Clinic', 'ACTIVE', '2026-02-03 07:22:58'),
(22, 3, 'Unisex Salon', 'ACTIVE', '2026-02-03 07:22:58'),
(23, 4, 'Interior Decor', 'ACTIVE', '2026-02-03 07:22:58'),
(24, 4, 'Curtains & Blinds', 'ACTIVE', '2026-02-03 07:22:58'),
(25, 4, 'Wallpapers', 'ACTIVE', '2026-02-03 07:22:58'),
(26, 4, 'Lighting Solutions', 'ACTIVE', '2026-02-03 07:22:58'),
(27, 4, 'Home Accessories', 'ACTIVE', '2026-02-03 07:22:58'),
(28, 4, 'Modular Furniture', 'ACTIVE', '2026-02-03 07:22:58'),
(29, 5, 'Full Wedding Planning', 'ACTIVE', '2026-02-03 07:22:58'),
(30, 5, 'Wedding Decoration', 'ACTIVE', '2026-02-03 07:22:58'),
(31, 5, 'Wedding Catering', 'ACTIVE', '2026-02-03 07:22:58'),
(32, 5, 'Wedding Photography', 'ACTIVE', '2026-02-03 07:22:58'),
(33, 5, 'Wedding Makeup', 'ACTIVE', '2026-02-03 07:22:58'),
(34, 5, 'Wedding Invitations', 'ACTIVE', '2026-02-03 07:22:58'),
(35, 6, 'Schools', 'ACTIVE', '2026-02-03 07:22:58'),
(36, 6, 'Colleges', 'ACTIVE', '2026-02-03 07:22:58'),
(37, 6, 'Tuition Centers', 'ACTIVE', '2026-02-03 07:22:58'),
(38, 6, 'Online Courses', 'ACTIVE', '2026-02-03 07:22:58'),
(39, 6, 'Skill Development', 'ACTIVE', '2026-02-03 07:22:58'),
(40, 6, 'Competitive Exam Coaching', 'ACTIVE', '2026-02-03 07:22:58'),
(41, 7, 'Furniture Rental', 'ACTIVE', '2026-02-03 07:22:58'),
(42, 7, 'Vehicle Rental', 'ACTIVE', '2026-02-03 07:22:58'),
(43, 7, 'Event Equipment Rental', 'ACTIVE', '2026-02-03 07:22:58'),
(44, 7, 'Camera & Accessories Rental', 'ACTIVE', '2026-02-03 07:22:58'),
(45, 7, 'Machinery Rental', 'ACTIVE', '2026-02-03 07:22:58'),
(46, 8, 'Multi-Speciality Hospital', 'ACTIVE', '2026-02-03 07:22:58'),
(47, 8, 'Clinic', 'ACTIVE', '2026-02-03 07:22:58'),
(48, 8, 'Diagnostic Center', 'ACTIVE', '2026-02-03 07:22:58'),
(49, 8, 'Maternity Hospital', 'ACTIVE', '2026-02-03 07:22:58'),
(50, 8, 'Eye Hospital', 'ACTIVE', '2026-02-03 07:22:58'),
(51, 8, 'Children Hospital', 'ACTIVE', '2026-02-03 07:22:58'),
(52, 9, 'Civil Contractor', 'ACTIVE', '2026-02-03 07:22:58'),
(53, 9, 'Electrical Contractor', 'ACTIVE', '2026-02-03 07:22:58'),
(54, 9, 'Plumbing Contractor', 'ACTIVE', '2026-02-03 07:22:58'),
(55, 9, 'Interior Contractor', 'ACTIVE', '2026-02-03 07:22:58'),
(56, 9, 'Painting Contractor', 'ACTIVE', '2026-02-03 07:22:58'),
(57, 10, 'Pet Store', 'ACTIVE', '2026-02-03 07:22:58'),
(58, 10, 'Pet Grooming', 'ACTIVE', '2026-02-03 07:22:58'),
(59, 10, 'Veterinary Clinic', 'ACTIVE', '2026-02-03 07:22:58'),
(60, 10, 'Pet Boarding', 'ACTIVE', '2026-02-03 07:22:58'),
(61, 10, 'Pet Food Supplier', 'ACTIVE', '2026-02-03 07:22:58'),
(62, 11, 'Mens PG', 'ACTIVE', '2026-02-03 07:22:58'),
(63, 11, 'Womens PG', 'ACTIVE', '2026-02-03 07:22:58'),
(64, 11, 'Student Hostel', 'ACTIVE', '2026-02-03 07:22:58'),
(65, 11, 'Working Professionals PG', 'ACTIVE', '2026-02-03 07:22:58'),
(66, 11, 'Paying Guest with Food', 'ACTIVE', '2026-02-03 07:22:58'),
(67, 12, 'Property Dealer', 'ACTIVE', '2026-02-03 07:22:58'),
(68, 12, 'Rental Agent', 'ACTIVE', '2026-02-03 07:22:58'),
(69, 12, 'Commercial Property', 'ACTIVE', '2026-02-03 07:22:58'),
(70, 12, 'Residential Property', 'ACTIVE', '2026-02-03 07:22:58'),
(71, 12, 'Land / Plot Sales', 'ACTIVE', '2026-02-03 07:22:58'),
(72, 13, 'Dental Clinic', 'ACTIVE', '2026-02-03 07:22:58'),
(73, 13, 'Orthodontist', 'ACTIVE', '2026-02-03 07:22:58'),
(74, 13, 'Implant Specialist', 'ACTIVE', '2026-02-03 07:22:58'),
(75, 13, 'Pediatric Dentist', 'ACTIVE', '2026-02-03 07:22:58'),
(76, 13, 'Cosmetic Dentistry', 'ACTIVE', '2026-02-03 07:22:58'),
(77, 14, 'Fitness Gym', 'ACTIVE', '2026-02-03 07:22:58'),
(78, 14, 'CrossFit', 'ACTIVE', '2026-02-03 07:22:58'),
(79, 14, 'Personal Training', 'ACTIVE', '2026-02-03 07:22:58'),
(80, 14, 'Yoga Center', 'ACTIVE', '2026-02-03 07:22:58'),
(81, 14, 'Zumba / Aerobics', 'ACTIVE', '2026-02-03 07:22:58'),
(82, 15, 'Personal Loan', 'ACTIVE', '2026-02-03 07:22:58'),
(83, 15, 'Home Loan', 'ACTIVE', '2026-02-03 07:22:58'),
(84, 15, 'Business Loan', 'ACTIVE', '2026-02-03 07:22:58'),
(85, 15, 'Vehicle Loan', 'ACTIVE', '2026-02-03 07:22:58'),
(86, 15, 'Gold Loan', 'ACTIVE', '2026-02-03 07:22:58'),
(87, 16, 'Corporate Events', 'ACTIVE', '2026-02-03 07:22:58'),
(88, 16, 'Birthday Events', 'ACTIVE', '2026-02-03 07:22:58'),
(89, 16, 'Wedding Events', 'ACTIVE', '2026-02-03 07:22:58'),
(90, 16, 'Cultural Events', 'ACTIVE', '2026-02-03 07:22:58'),
(91, 16, 'Exhibition Management', 'ACTIVE', '2026-02-03 07:22:58'),
(92, 17, 'Two Wheeler Training', 'ACTIVE', '2026-02-03 07:22:58'),
(93, 17, 'Four Wheeler Training', 'ACTIVE', '2026-02-03 07:22:58'),
(94, 17, 'Heavy Vehicle Training', 'ACTIVE', '2026-02-03 07:22:58'),
(95, 17, 'Refresher Courses', 'ACTIVE', '2026-02-03 07:22:58'),
(96, 18, 'Local Shifting', 'ACTIVE', '2026-02-03 07:22:58'),
(97, 18, 'Domestic Relocation', 'ACTIVE', '2026-02-03 07:22:58'),
(98, 18, 'Office Relocation', 'ACTIVE', '2026-02-03 07:22:58'),
(99, 18, 'Warehouse Storage', 'ACTIVE', '2026-02-03 07:22:58'),
(100, 18, 'International Movers', 'ACTIVE', '2026-02-03 07:22:58'),
(101, 19, 'Local Courier', 'ACTIVE', '2026-02-03 07:22:58'),
(102, 19, 'Domestic Courier', 'ACTIVE', '2026-02-03 07:22:58'),
(103, 19, 'International Courier', 'ACTIVE', '2026-02-03 07:22:58'),
(104, 19, 'Same Day Delivery', 'ACTIVE', '2026-02-03 07:22:58'),
(105, 19, 'Logistics Service', 'ACTIVE', '2026-02-03 07:22:58'),
(106, 20, 'AC Installation', 'ACTIVE', '2026-02-03 07:22:58'),
(107, 20, 'AC Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(108, 20, 'AC Maintenance', 'ACTIVE', '2026-02-03 07:22:58'),
(109, 20, 'AMC Services', 'ACTIVE', '2026-02-03 07:22:58'),
(110, 20, 'Commercial AC Service', 'ACTIVE', '2026-02-03 07:22:58'),
(111, 21, 'Horoscope Reading', 'ACTIVE', '2026-02-03 07:22:58'),
(112, 21, 'Vastu Consultant', 'ACTIVE', '2026-02-03 07:22:58'),
(113, 21, 'Numerology', 'ACTIVE', '2026-02-03 07:22:58'),
(114, 21, 'Palm Reading', 'ACTIVE', '2026-02-03 07:22:58'),
(115, 21, 'Marriage Astrology', 'ACTIVE', '2026-02-03 07:22:58'),
(116, 22, 'Full Body Massage', 'ACTIVE', '2026-02-03 07:22:58'),
(117, 22, 'Ayurvedic Massage', 'ACTIVE', '2026-02-03 07:22:58'),
(118, 22, 'Therapy Massage', 'ACTIVE', '2026-02-03 07:22:58'),
(119, 22, 'Relaxation Massage', 'ACTIVE', '2026-02-03 07:22:58'),
(120, 22, 'Sports Massage', 'ACTIVE', '2026-02-03 07:22:58'),
(121, 23, 'Wedding Catering', 'ACTIVE', '2026-02-03 07:22:58'),
(122, 23, 'Corporate Catering', 'ACTIVE', '2026-02-03 07:22:58'),
(123, 23, 'Party Catering', 'ACTIVE', '2026-02-03 07:22:58'),
(124, 23, 'Veg Catering', 'ACTIVE', '2026-02-03 07:22:58'),
(125, 23, 'Non-Veg Catering', 'ACTIVE', '2026-02-03 07:22:58'),
(126, 24, 'Accounting Services', 'ACTIVE', '2026-02-03 07:22:58'),
(127, 24, 'Tax Filing', 'ACTIVE', '2026-02-03 07:22:58'),
(128, 24, 'GST Services', 'ACTIVE', '2026-02-03 07:22:58'),
(129, 24, 'Audit Services', 'ACTIVE', '2026-02-03 07:22:58'),
(130, 24, 'Company Registration', 'ACTIVE', '2026-02-03 07:22:58'),
(131, 25, 'Software Training', 'ACTIVE', '2026-02-03 07:22:58'),
(132, 25, 'Hardware Training', 'ACTIVE', '2026-02-03 07:22:58'),
(133, 25, 'Programming Courses', 'ACTIVE', '2026-02-03 07:22:58'),
(134, 25, 'Data Analytics', 'ACTIVE', '2026-02-03 07:22:58'),
(135, 25, 'IT Certification', 'ACTIVE', '2026-02-03 07:22:58'),
(136, 26, 'Laptop Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(137, 26, 'Desktop Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(138, 26, 'Hardware Replacement', 'ACTIVE', '2026-02-03 07:22:58'),
(139, 26, 'Software Installation', 'ACTIVE', '2026-02-03 07:22:58'),
(140, 26, 'Network Setup', 'ACTIVE', '2026-02-03 07:22:58'),
(141, 27, 'Car Service Center', 'ACTIVE', '2026-02-03 07:22:58'),
(142, 27, 'Mechanical Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(143, 27, 'Electrical Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(144, 27, 'Car Washing', 'ACTIVE', '2026-02-03 07:22:58'),
(145, 27, 'Accident Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(146, 28, 'Skin Clinic', 'ACTIVE', '2026-02-03 07:22:58'),
(147, 28, 'Hair Treatment', 'ACTIVE', '2026-02-03 07:22:58'),
(148, 28, 'Laser Treatment', 'ACTIVE', '2026-02-03 07:22:58'),
(149, 28, 'Cosmetic Dermatology', 'ACTIVE', '2026-02-03 07:22:58'),
(150, 28, 'Acne Treatment', 'ACTIVE', '2026-02-03 07:22:58'),
(151, 29, 'Residential Electrical Work', 'ACTIVE', '2026-02-03 07:22:58'),
(152, 29, 'Commercial Electrical Work', 'ACTIVE', '2026-02-03 07:22:58'),
(153, 29, 'Wiring & Fittings', 'ACTIVE', '2026-02-03 07:22:58'),
(154, 29, 'Inverter Installation', 'ACTIVE', '2026-02-03 07:22:58'),
(155, 29, 'Solar Installation', 'ACTIVE', '2026-02-03 07:22:58'),
(156, 30, 'Birthday Decoration', 'ACTIVE', '2026-02-03 07:22:58'),
(157, 30, 'Stage Decoration', 'ACTIVE', '2026-02-03 07:22:58'),
(158, 30, 'Balloon Decoration', 'ACTIVE', '2026-02-03 07:22:58'),
(159, 30, 'Sound & Lighting', 'ACTIVE', '2026-02-03 07:22:58'),
(160, 30, 'DJ Services', 'ACTIVE', '2026-02-03 07:22:58'),
(161, 31, 'Builders', 'ACTIVE', '2026-02-03 07:22:58'),
(162, 31, 'Promoters', 'ACTIVE', '2026-02-03 07:22:58'),
(163, 31, 'Property Developers', 'ACTIVE', '2026-02-03 07:22:58'),
(164, 31, 'Construction Companies', 'ACTIVE', '2026-02-03 07:22:58'),
(165, 32, 'Steel Fabrication', 'ACTIVE', '2026-02-03 07:22:58'),
(166, 32, 'Aluminium Fabrication', 'ACTIVE', '2026-02-03 07:22:58'),
(167, 32, 'Gate & Grill Work', 'ACTIVE', '2026-02-03 07:22:58'),
(168, 32, 'Industrial Fabrication', 'ACTIVE', '2026-02-03 07:22:58'),
(169, 33, 'Sofa Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(170, 33, 'Chair Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(171, 33, 'Wood Polishing', 'ACTIVE', '2026-02-03 07:22:58'),
(172, 33, 'Modular Furniture Repair', 'ACTIVE', '2026-02-03 07:22:58'),
(173, 34, 'Home Cleaning', 'ACTIVE', '2026-02-03 07:22:59'),
(174, 34, 'Office Cleaning', 'ACTIVE', '2026-02-03 07:22:59'),
(175, 34, 'Deep Cleaning', 'ACTIVE', '2026-02-03 07:22:59'),
(176, 34, 'Pest Cleaning', 'ACTIVE', '2026-02-03 07:22:59'),
(177, 34, 'Manpower Supply', 'ACTIVE', '2026-02-03 07:22:59'),
(178, 35, 'Art & Craft', 'ACTIVE', '2026-02-03 07:22:59'),
(179, 35, 'Music Classes', 'ACTIVE', '2026-02-03 07:22:59'),
(180, 35, 'Dance Classes', 'ACTIVE', '2026-02-03 07:22:59'),
(181, 35, 'Sports Coaching', 'ACTIVE', '2026-02-03 07:22:59'),
(182, 35, 'Hobby Workshops', 'ACTIVE', '2026-02-03 07:22:59'),
(183, 36, 'Residential Interior', 'ACTIVE', '2026-02-03 07:22:59'),
(184, 36, 'Commercial Interior', 'ACTIVE', '2026-02-03 07:22:59'),
(185, 36, 'Modular Kitchen', 'ACTIVE', '2026-02-03 07:22:59'),
(186, 36, 'Office Interior', 'ACTIVE', '2026-02-03 07:22:59'),
(187, 37, 'Website Design', 'ACTIVE', '2026-02-03 07:22:59'),
(188, 37, 'Web Development', 'ACTIVE', '2026-02-03 07:22:59'),
(189, 37, 'E-commerce Development', 'ACTIVE', '2026-02-03 07:22:59'),
(190, 37, 'SEO Services', 'ACTIVE', '2026-02-03 07:22:59'),
(191, 37, 'Digital Marketing', 'ACTIVE', '2026-02-03 07:22:59'),
(192, 38, 'Gold Jewellery', 'ACTIVE', '2026-02-03 07:22:59'),
(193, 38, 'Diamond Jewellery', 'ACTIVE', '2026-02-03 07:22:59'),
(194, 38, 'Silver Jewellery', 'ACTIVE', '2026-02-03 07:22:59'),
(195, 38, 'Custom Jewellery', 'ACTIVE', '2026-02-03 07:22:59'),
(196, 38, 'Wholesale Jewellery', 'ACTIVE', '2026-02-03 07:22:59'),
(197, 39, 'Civil Lawyer', 'ACTIVE', '2026-02-03 07:22:59'),
(198, 39, 'Criminal Lawyer', 'ACTIVE', '2026-02-03 07:22:59'),
(199, 39, 'Family Lawyer', 'ACTIVE', '2026-02-03 07:22:59'),
(200, 39, 'Corporate Lawyer', 'ACTIVE', '2026-02-03 07:22:59'),
(201, 39, 'Property Lawyer', 'ACTIVE', '2026-02-03 07:22:59'),
(202, 40, 'Goods Transport', 'ACTIVE', '2026-02-03 07:22:59'),
(203, 40, 'Truck Services', 'ACTIVE', '2026-02-03 07:22:59'),
(204, 40, 'Mini Load Vehicles', 'ACTIVE', '2026-02-03 07:22:59'),
(205, 40, 'Logistics Companies', 'ACTIVE', '2026-02-03 07:22:59'),
(206, 41, 'Wedding Photography', 'ACTIVE', '2026-02-03 07:22:59'),
(207, 41, 'Event Photography', 'ACTIVE', '2026-02-03 07:22:59'),
(208, 41, 'Product Photography', 'ACTIVE', '2026-02-03 07:22:59'),
(209, 41, 'Portrait Photography', 'ACTIVE', '2026-02-03 07:22:59'),
(210, 41, 'Videography', 'ACTIVE', '2026-02-03 07:22:59'),
(211, 42, 'Home Nursing', 'ACTIVE', '2026-02-03 07:22:59'),
(212, 42, 'Elder Care', 'ACTIVE', '2026-02-03 07:22:59'),
(213, 42, 'Patient Care', 'ACTIVE', '2026-02-03 07:22:59'),
(214, 42, 'Post-Operative Care', 'ACTIVE', '2026-02-03 07:22:59'),
(215, 43, 'Offset Printing', 'ACTIVE', '2026-02-03 07:22:59'),
(216, 43, 'Digital Printing', 'ACTIVE', '2026-02-03 07:22:59'),
(217, 43, 'Flex Printing', 'ACTIVE', '2026-02-03 07:22:59'),
(218, 43, 'Visiting Card Printing', 'ACTIVE', '2026-02-03 07:22:59'),
(219, 43, 'Book Publishing', 'ACTIVE', '2026-02-03 07:22:59'),
(220, 44, 'Job Consultancy', 'ACTIVE', '2026-02-03 07:22:59'),
(221, 44, 'HR Services', 'ACTIVE', '2026-02-03 07:22:59'),
(222, 44, 'Manpower Supply', 'ACTIVE', '2026-02-03 07:22:59'),
(223, 44, 'Staffing Solutions', 'ACTIVE', '2026-02-03 07:22:59'),
(224, 45, 'Residential Pest Control', 'ACTIVE', '2026-02-03 07:22:59'),
(225, 45, 'Commercial Pest Control', 'ACTIVE', '2026-02-03 07:22:59'),
(226, 45, 'Termite Control', 'ACTIVE', '2026-02-03 07:22:59'),
(227, 45, 'Rodent Control', 'ACTIVE', '2026-02-03 07:22:59'),
(228, 46, 'Interior Painting', 'ACTIVE', '2026-02-03 07:22:59'),
(229, 46, 'Exterior Painting', 'ACTIVE', '2026-02-03 07:22:59'),
(230, 46, 'Texture Painting', 'ACTIVE', '2026-02-03 07:22:59'),
(231, 46, 'Waterproofing', 'ACTIVE', '2026-02-03 07:22:59'),
(232, 47, 'Metal Scrap', 'ACTIVE', '2026-02-03 07:22:59'),
(233, 47, 'Plastic Scrap', 'ACTIVE', '2026-02-03 07:22:59'),
(234, 47, 'E-Waste Scrap', 'ACTIVE', '2026-02-03 07:22:59'),
(235, 47, 'Paper Scrap', 'ACTIVE', '2026-02-03 07:22:59'),
(236, 48, 'CCTV Installation', 'ACTIVE', '2026-02-03 07:22:59'),
(237, 48, 'Alarm Systems', 'ACTIVE', '2026-02-03 07:22:59'),
(238, 48, 'Access Control', 'ACTIVE', '2026-02-03 07:22:59'),
(239, 48, 'Surveillance Solutions', 'ACTIVE', '2026-02-03 07:22:59'),
(240, 49, 'Academic Coaching', 'ACTIVE', '2026-02-03 07:22:59'),
(241, 49, 'Competitive Exams', 'ACTIVE', '2026-02-03 07:22:59'),
(242, 49, 'Sports Coaching', 'ACTIVE', '2026-02-03 07:22:59'),
(243, 49, 'Skill Coaching', 'ACTIVE', '2026-02-03 07:22:59'),
(244, 50, 'Technical Training', 'ACTIVE', '2026-02-03 07:22:59'),
(245, 50, 'Skill Development', 'ACTIVE', '2026-02-03 07:22:59'),
(246, 50, 'Industrial Training', 'ACTIVE', '2026-02-03 07:22:59'),
(247, 50, 'Trade Courses', 'ACTIVE', '2026-02-03 07:22:59'),
(248, 51, 'Company Registration', 'ACTIVE', '2026-02-03 07:22:59'),
(249, 51, 'GST Registration', 'ACTIVE', '2026-02-03 07:22:59'),
(250, 51, 'MSME Registration', 'ACTIVE', '2026-02-03 07:22:59'),
(251, 51, 'Trade License', 'ACTIVE', '2026-02-03 07:22:59'),
(252, 51, 'Legal Documentation', 'ACTIVE', '2026-02-03 07:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('admin','business') NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('INVITED','ACTIVE','SUSPENDED') DEFAULT 'INVITED',
  `email_verified` tinyint(1) DEFAULT 0,
  `profile_complete` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `name`, `email`, `mobile`, `username`, `password`, `status`, `email_verified`, `profile_complete`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Admin', 'admin@qnexus.in', '9500744441', 'admin', '$2y$10$oXSqja5SqSzKXUG8v29ELe4CyCt.KE5ZUG/eJRNV7i1P9Uvr3clWS', 'ACTIVE', 1, 1, '2026-02-03 06:23:53', '2026-02-06 06:58:28'),
(2, 'business', 'karthick', 'developerkarthickraj@gmail.com', '8428471708', NULL, '$2y$10$LomrJWq.lDxj4vtVKtn/ReiRNFiGj/n7Bb47K.DabviQBvlx6GoXa', 'ACTIVE', 1, 0, '2026-02-03 06:26:24', '2026-02-03 07:03:58'),
(11, 'business', 'jeeva', 'karthickraj2912@gmail.com', '8122513669', NULL, '$2y$10$Fs7D9A7UTmJEtvXBQ4LCBuvLVoMsyrcd8YSOdRBcNqALO/wPj8khS', 'ACTIVE', 1, 0, '2026-02-03 07:37:51', '2026-02-03 07:41:06'),
(12, 'business', 'ksv', 'vasanthchockaingam4@gmail.com', '6379920700', NULL, NULL, 'INVITED', 0, 0, '2026-02-11 07:38:01', '2026-02-11 07:38:01'),
(13, 'business', 'kanna', 'karthickraj2913@gmail.com', '8428471709', NULL, NULL, 'INVITED', 0, 0, '2026-02-13 05:08:45', '2026-02-13 05:08:45'),
(14, 'business', 'ronsia', 'ronsiapatheesj@gmail.com', '9688343484', NULL, '$2y$10$tIqvP6a08Cr14.MkRZJGuOyb65XpNpXYcjZuUwivKC2NRiGDCqxe.', 'ACTIVE', 1, 0, '2026-02-13 05:13:33', '2026-02-13 05:14:09'),
(15, 'business', 'thulasi garments', 'thulasiragulraj@gmail.com', '8825920268', NULL, '$2y$10$0Rk9NuiZF/93qdgPQK5gjOSU7DttlRBZlMUkJ9.5PDsXX5FN.2lN.', 'ACTIVE', 1, 0, '2026-03-23 05:36:13', '2026-03-23 05:37:09'),
(16, 'business', 'ragul supermarkets', 'ragulthulasi82@gmail.com', '9013408669', NULL, '$2y$10$LaIaf3fOBUxrlmOIa5wzBOTwPGqxblTykXjK96ooQ5zgLXqYvFBe2', 'ACTIVE', 1, 0, '2026-03-24 07:17:03', '2026-03-24 07:19:41'),
(17, 'business', 'kabil supermarkets', 'kabil4479@gmail.com', '9023408669', NULL, '$2y$10$qqjLWJz0XZsQyE1QHQ9G6OXAA/sMa5lzNNY0pNo81ydexNbeNuyNW', 'ACTIVE', 1, 0, '2026-03-24 07:32:47', '2026-03-24 07:33:57'),
(18, 'business', 'manoj supermarkets', 'kragul653@gmail.com', '9523408669', NULL, '$2y$10$7qdQcdCgLzC/4H4l7juCPemJnBbhCrQT43CmSZcV.KKZ0fJbouH6O', 'ACTIVE', 1, 0, '2026-03-24 08:14:55', '2026-03-24 08:15:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_interest_map`
--

CREATE TABLE `user_interest_map` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `sub_category_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_interest_map`
--

INSERT INTO `user_interest_map` (`id`, `user_id`, `category_id`, `sub_category_id`, `score`, `updated_at`) VALUES
(1, 11, 1, 1, 1, '2026-02-03 09:30:14'),
(2, 11, 6, 35, 1, '2026-02-03 09:30:17'),
(3, 2, 6, 35, 4, '2026-03-23 06:01:37'),
(6, 15, 2, 12, 1, '2026-03-23 05:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `user_invites`
--

CREATE TABLE `user_invites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_invites`
--

INSERT INTO `user_invites` (`id`, `user_id`, `token`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 2, '5c6370533b0c488d7c4393802310315dee6dd22958f8f0db5f009ad671837e68', '2026-02-04 11:56:24', '2026-02-03 12:33:58', '2026-02-03 06:26:24'),
(10, 11, 'a728009a40952382333268148145714298cddd4e7ecdebe8f3e48a64579504ac', '2026-02-04 13:07:51', '2026-02-03 13:11:06', '2026-02-03 07:37:51'),
(11, 12, '14ccd09a00048faae32aeebdf96323913921eb32b5e4a14401eec29901841edd', '2026-02-12 07:38:01', NULL, '2026-02-11 07:38:01'),
(12, 13, '7a77c0c7c7055d7b81720202fdf75a6e51264fce461e90ed9026165d15b1b63d', '2026-02-14 05:08:45', NULL, '2026-02-13 05:08:45'),
(13, 14, '9217a58c8c845fcaaf005db9373ce9529f0138a2a12014e1a3e3c8272f208453', '2026-02-14 05:13:33', '2026-02-13 05:14:09', '2026-02-13 05:13:33'),
(14, 15, '7303bca98fde93fa68015366bfdc47c4b29d639c2329374e32859a62c9be9051', '2026-03-24 05:36:13', '2026-03-23 05:37:09', '2026-03-23 05:36:13'),
(15, 16, 'fc65479a63e8c653a04b38654c1d51ddf19ba7e0a0dac982f72215fd0b5f86d1', '2026-03-25 12:47:03', '2026-03-24 12:49:41', '2026-03-24 07:17:03'),
(16, 17, '24426a7b7402132259c0ad38cf49bbe633a44327126f8e765dae7bbf58903384', '2026-03-25 13:02:47', '2026-03-24 13:03:57', '2026-03-24 07:32:47'),
(17, 18, '48bf306fdcf97ef9b3de748b1ab8c482106114c9de7c25e39ace92337ff0d1b6', '2026-03-25 13:44:55', '2026-03-24 13:45:55', '2026-03-24 08:14:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_last_seen`
--

CREATE TABLE `user_last_seen` (
  `user_id` int(11) NOT NULL,
  `last_seen` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_meetings`
--

CREATE TABLE `user_meetings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `meeting_date` datetime NOT NULL,
  `reminder_days_before` int(11) DEFAULT 1,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `status` enum('SCHEDULED','COMPLETED','CANCELLED') DEFAULT 'SCHEDULED',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `reminder_minutes_before` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_meetings`
--

INSERT INTO `user_meetings` (`id`, `user_id`, `title`, `description`, `meeting_date`, `reminder_days_before`, `reminder_sent`, `status`, `created_at`, `updated_at`, `reminder_minutes_before`) VALUES
(1, 15, 'Client Meeting Updated', 'Discuss final proposal', '2026-04-10 11:00:00', 2, 0, 'SCHEDULED', '2026-03-26 07:40:58', '2026-03-26 08:09:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_deals`
--

CREATE TABLE `vendor_deals` (
  `id` int(11) NOT NULL,
  `initiator_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED','COMPLETED') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_deals`
--

INSERT INTO `vendor_deals` (`id`, `initiator_id`, `receiver_id`, `title`, `description`, `status`, `created_at`, `updated_at`) VALUES
(3, 15, 18, 'Wedding Photography Deal', 'Need full coverage for event', 'COMPLETED', '2026-03-25 08:51:14', '2026-03-25 11:06:13');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_deal_messages`
--

CREATE TABLE `vendor_deal_messages` (
  `id` int(11) NOT NULL,
  `deal_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_deal_messages`
--

INSERT INTO `vendor_deal_messages` (`id`, `deal_id`, `sender_id`, `receiver_id`, `message`, `created_at`) VALUES
(2, 3, 15, 18, 'Bro deal confirm pannalama?', '2026-03-25 09:33:30'),
(3, 3, 18, 15, 'yes, bro', '2026-03-25 10:49:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `business_user_id` (`business_user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `business_profiles`
--
ALTER TABLE `business_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `sub_category_id` (`sub_category_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_chat` (`user_one`,`user_two`),
  ADD KEY `user_two` (`user_two`);

--
-- Indexes for table `chat_activity_log`
--
ALTER TABLE `chat_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_id` (`chat_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_id` (`chat_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_message` (`message`(100));

--
-- Indexes for table `chat_typing`
--
ALTER TABLE `chat_typing`
  ADD PRIMARY KEY (`chat_id`,`user_id`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `communities`
--
ALTER TABLE `communities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `community_invites`
--
ALTER TABLE `community_invites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_id` (`community_id`),
  ADD KEY `invited_by` (`invited_by`),
  ADD KEY `invited_user_id` (`invited_user_id`);

--
-- Indexes for table `community_members`
--
ALTER TABLE `community_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`community_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `community_messages`
--
ALTER TABLE `community_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_id` (`community_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `event_communities`
--
ALTER TABLE `event_communities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `community_id` (`community_id`);

--
-- Indexes for table `event_vendors`
--
ALTER TABLE `event_vendors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `post_media`
--
ALTER TABLE `post_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `post_reactions`
--
ALTER TABLE `post_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`event_id`,`deal_id`,`reviewer_id`,`vendor_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `sub_categories`
--
ALTER TABLE `sub_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_interest_map`
--
ALTER TABLE `user_interest_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_interest` (`user_id`,`category_id`,`sub_category_id`);

--
-- Indexes for table `user_invites`
--
ALTER TABLE `user_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_last_seen`
--
ALTER TABLE `user_last_seen`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_meetings`
--
ALTER TABLE `user_meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `meeting_date` (`meeting_date`);

--
-- Indexes for table `vendor_deals`
--
ALTER TABLE `vendor_deals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `initiator_id` (`initiator_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `vendor_deal_messages`
--
ALTER TABLE `vendor_deal_messages`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_messages`
--
ALTER TABLE `admin_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_profiles`
--
ALTER TABLE `business_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_activity_log`
--
ALTER TABLE `chat_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `communities`
--
ALTER TABLE `communities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `community_invites`
--
ALTER TABLE `community_invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `community_members`
--
ALTER TABLE `community_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `community_messages`
--
ALTER TABLE `community_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `event_communities`
--
ALTER TABLE `event_communities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_vendors`
--
ALTER TABLE `event_vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `post_comments`
--
ALTER TABLE `post_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `post_media`
--
ALTER TABLE `post_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `post_reactions`
--
ALTER TABLE `post_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sub_categories`
--
ALTER TABLE `sub_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `user_interest_map`
--
ALTER TABLE `user_interest_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_invites`
--
ALTER TABLE `user_invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_meetings`
--
ALTER TABLE `user_meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendor_deals`
--
ALTER TABLE `vendor_deals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendor_deal_messages`
--
ALTER TABLE `vendor_deal_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD CONSTRAINT `admin_messages_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `admin_messages_ibfk_2` FOREIGN KEY (`business_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `business_profiles`
--
ALTER TABLE `business_profiles`
  ADD CONSTRAINT `business_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `business_profiles_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `business_profiles_ibfk_3` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_categories` (`id`);

--
-- Constraints for table `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`user_one`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_ibfk_2` FOREIGN KEY (`user_two`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_activity_log`
--
ALTER TABLE `chat_activity_log`
  ADD CONSTRAINT `chat_activity_log_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communities`
--
ALTER TABLE `communities`
  ADD CONSTRAINT `communities_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_invites`
--
ALTER TABLE `community_invites`
  ADD CONSTRAINT `community_invites_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_invites_ibfk_2` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_invites_ibfk_3` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_members`
--
ALTER TABLE `community_members`
  ADD CONSTRAINT `community_members_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_messages`
--
ALTER TABLE `community_messages`
  ADD CONSTRAINT `community_messages_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_communities`
--
ALTER TABLE `event_communities`
  ADD CONSTRAINT `event_communities_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_communities_ibfk_2` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_vendors`
--
ALTER TABLE `event_vendors`
  ADD CONSTRAINT `event_vendors_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_vendors_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD CONSTRAINT `post_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_media`
--
ALTER TABLE `post_media`
  ADD CONSTRAINT `post_media_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_reactions`
--
ALTER TABLE `post_reactions`
  ADD CONSTRAINT `post_reactions_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_categories`
--
ALTER TABLE `sub_categories`
  ADD CONSTRAINT `sub_categories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_interest_map`
--
ALTER TABLE `user_interest_map`
  ADD CONSTRAINT `user_interest_map_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_invites`
--
ALTER TABLE `user_invites`
  ADD CONSTRAINT `user_invites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
