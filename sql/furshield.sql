-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 13, 2025 at 10:47 AM
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
-- Database: `furshield`
--

-- --------------------------------------------------------

--
-- Table structure for table `addoption`
--

CREATE TABLE `addoption` (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `species` varchar(50) NOT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `age` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image_alt` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `urgent_until` date DEFAULT NULL,
  `spotlight` tinyint(1) DEFAULT 0,
  `status` enum('available','pending','adopted') DEFAULT 'available',
  `hold_until` datetime DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addoption`
--

INSERT INTO `addoption` (`id`, `created_by`, `shelter_id`, `name`, `species`, `breed`, `age`, `gender`, `avatar`, `image`, `category`, `image_alt`, `description`, `featured`, `urgent_until`, `spotlight`, `status`, `hold_until`, `city`, `created_at`, `updated_at`) VALUES
(11, 36, 36, 'Huskiey', 'Dog', 'wolf', '2', 'Male', 'pet_20250913_084056_f3f5ef50.jpg', 'pet_20250913_084056_f3f5ef50.jpg', '', '', '', 1, NULL, 0, 'pending', NULL, 'karachi', '2025-09-13 06:40:56', '2025-09-13 07:47:59'),
(12, 36, 36, 'Rabit', 'Other', 'Angora', '1', 'Female', 'pet_20250913_084223_ce0e44c0.jpg', 'pet_20250913_084223_ce0e44c0.jpg', '', '', 'Based on its name, you might think the Alaska rabbit originated in the state of Alaska, but the jet-black breed is in fact native to Germany, where it was created primarily as a fur rabbit (non-pet), although the attractive-looking Alaska rabbit can make a fine pet. Though it was at one time recognized by the American Rabbit Breeders Association, the breed is no longer recognized by ARBA. The Alaska is recognized by the British Rabbit Council in the United Kingdom.', 0, NULL, 0, 'available', NULL, '', '2025-09-13 06:42:23', '2025-09-13 06:42:23'),
(13, 36, 36, 'Samin', 'Cat', 'Siamese', '1', 'Female', 'pet_20250913_084522_4847e9e5.jpg', 'pet_20250913_084522_4847e9e5.jpg', 'cat', '', 'The cat, also referred to as the domestic cat or house cat, is a small domesticated carnivorous mammal. It is the only domesticated species of the family Felidae. Advances in archaeology and genetics have shown that the domestication of the cat occurred in the Near East around', 0, NULL, 0, 'available', NULL, '', '2025-09-13 06:45:22', '2025-09-13 06:45:22'),
(14, 36, 36, 'Samin', 'Cat', 'Siamese', '1', 'Female', 'pet_20250913_091606_518b8aea.jpg', 'pet_20250913_091606_518b8aea.jpg', '', '', 'Each pouch is packed with essential nutrients designed to support your dog’s overall health and vitality. With a carefully formulated recipe, you can rest assured that your furry companion is getting the balanced diet they deserve.', 0, NULL, 0, 'pending', NULL, 'karachi', '2025-09-13 07:16:06', '2025-09-13 07:46:39'),
(15, 44, 44, 'Rabbit', 'Other', 'Rabbit2', '2year', 'Male', 'pet_20250913_095712_1f20afe3.jpg', 'pet_20250913_095712_1f20afe3.jpg', 'BAby', '', '', 1, '2025-09-13', 1, 'available', NULL, 'karachi', '2025-09-13 07:57:12', '2025-09-13 07:57:12');

-- --------------------------------------------------------

--
-- Table structure for table `adoption_agreements`
--

CREATE TABLE `adoption_agreements` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `signed_owner_at` datetime DEFAULT NULL,
  `signed_shelter_at` datetime DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `adoption_applications`
--

CREATE TABLE `adoption_applications` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `status` enum('submitted','under_review','pre_approved','meet_scheduled','home_check','approved','rejected','withdrawn','expired','completed') NOT NULL DEFAULT 'submitted',
  `score` tinyint(3) UNSIGNED DEFAULT NULL,
  `answers_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers_json`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `adoption_applications`
--
DELIMITER $$
CREATE TRIGGER `trg_app_before_insert` BEFORE INSERT ON `adoption_applications` FOR EACH ROW BEGIN
  IF NEW.shelter_id IS NULL OR NEW.shelter_id = 0 THEN
    SET NEW.shelter_id = (
      SELECT ao.shelter_id
      FROM addoption ao
      WHERE ao.id = NEW.pet_id
      LIMIT 1
    );
  END IF;

  IF NEW.shelter_id IS NULL OR NEW.shelter_id = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cannot derive shelter_id from addoption';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `adoption_meetings`
--

CREATE TABLE `adoption_meetings` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `type` enum('meet','home_check') NOT NULL DEFAULT 'meet',
  `slot_start` datetime NOT NULL,
  `slot_end` datetime NOT NULL,
  `status` enum('scheduled','completed','no_show','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `adoption_requests`
--

CREATE TABLE `adoption_requests` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `adopter_user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_events`
--

CREATE TABLE `application_events` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `event` varchar(50) NOT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `vet_id` int(11) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `appointment_time` datetime NOT NULL,
  `status` enum('pending','approved','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blogs`
--

INSERT INTO `blogs` (`id`, `title`, `slug`, `category`, `image`, `content`, `is_active`, `created_at`) VALUES
(1, '5 Tips for a Healthy Dog', 'healthy-dog-tips', 'Care', '/uploads/blo/blog-3b518720.jpg', 'From balanced diet to exercise, here are 5 simple tips to keep your dog healthy.', 1, '2025-09-09 19:38:17'),
(2, 'How to Prepare for Adopting a Cat', 'adopting-a-cat', 'Adoption', '/uploads/blo/360_F_266724172_Iy8gdKgMa7XmrhYYxLCxyhx6J7070Pr8.jpg', 'Adopting a cat is a big responsibility. Here’s what you need to prepare.', 1, '2025-09-09 19:38:17'),
(3, 'Tips for a Healthy Dog', NULL, NULL, '/uploads/blo/blog-c6e372d5.jpg', 'Tips for a Healthy Dog', 1, '2025-09-13 04:41:34'),
(4, 'rabit', NULL, NULL, '/uploads/blo/blog-50c185a1.jpg', 'My blog is a growing resource covering all aspects of pet rabbit ownership. Starting with basic rabbit care, including diet, housing and health. Along with other general and advanced topics. So, whether you’re a new, or an experienced rabbit owner, you’ll find practical articles and advice, as well my rabbit stories to enrich your rabbit care journey.', 1, '2025-09-13 06:52:04');

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `logo`, `link`, `is_active`, `created_at`) VALUES
(1, 'uploads/brands/pedigree.png', 'https://www.pedigree.com', 1, '2025-09-09 19:38:17'),
(2, 'uploads/brands/whiskas.png', 'https://www.whiskas.com', 1, '2025-09-09 19:38:17');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `ua` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(160) NOT NULL,
  `date` date NOT NULL,
  `city` varchar(80) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `date`, `city`, `image`, `link`, `is_active`, `created_at`) VALUES
(1, 'Free Vaccination Camp', '2025-09-20', 'Karachi', '/uploads/events/e-c7c9ffcc.jpg', 'https://furshield.com/events/vaccine-camp', 1, '2025-09-09 19:38:17'),
(2, 'Pet Adoption Fair', '2025-10-05', 'Lahore', '/uploads/events/e-bd279704.jpg', 'https://furshield.com/events/adoption-fair', 1, '2025-09-09 19:38:17'),
(3, 'Dog race', '2025-09-11', 'Lahore', '/uploads/events/e-045094a9.jpg', 'https://iditarod.com/', 1, '2025-09-13 06:55:28'),
(6, 'Dog & Pet Events', '2025-09-25', 'karachi', '/uploads/events/e-b6281cb9.jpg', 'https://www.protectivity.com/knowledge-centre/dog-pet-events-2024/', 1, '2025-09-13 07:14:21');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` varchar(220) NOT NULL,
  `answer` text NOT NULL,
  `is_top` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `is_top`, `is_active`, `created_at`) VALUES
(1, 'How do I register as a vet?', 'Go to Register Vet page, fill details, wait for admin approval.', 1, 1, '2025-09-09 19:38:17'),
(2, 'Is online payment available?', 'Currently no, payments are simulated only.', 0, 1, '2025-09-09 19:38:17');

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `record_type` enum('vaccine','allergy_test','lab_report','xray','prescription','other') NOT NULL,
  `title` varchar(160) NOT NULL,
  `record_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_mime` varchar(80) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`id`, `owner_id`, `pet_id`, `record_type`, `title`, `record_date`, `notes`, `file_path`, `file_mime`, `file_size`, `created_at`) VALUES
(3, 42, 19, '', 'Leg Xray', '2025-09-08', 'Leg injury', 'uploads/records/hr_20250913_094547_a776c83f.jpg', 'image/jpeg', 18030, '2025-09-13 07:45:47'),
(4, 46, 20, '', 'Deworming booster', '2025-09-17', 'ffahfoi', NULL, NULL, NULL, '2025-09-13 08:31:40');

-- --------------------------------------------------------

--
-- Table structure for table `homepage_settings`
--

CREATE TABLE `homepage_settings` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `hero_title` varchar(160) DEFAULT NULL,
  `hero_subtitle` varchar(220) DEFAULT NULL,
  `hero_image` varchar(255) DEFAULT NULL,
  `cta_primary_text` varchar(60) DEFAULT NULL,
  `cta_primary_url` varchar(255) DEFAULT NULL,
  `cta_secondary_text` varchar(60) DEFAULT NULL,
  `cta_secondary_url` varchar(255) DEFAULT NULL,
  `show_adoption` tinyint(1) NOT NULL DEFAULT 1,
  `adoption_mode` enum('featured','latest') NOT NULL DEFAULT 'latest',
  `show_products` tinyint(1) NOT NULL DEFAULT 1,
  `products_mode` enum('featured','latest') NOT NULL DEFAULT 'latest',
  `show_testimonials` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `homepage_settings`
--

INSERT INTO `homepage_settings` (`id`, `hero_title`, `hero_subtitle`, `hero_image`, `cta_primary_text`, `cta_primary_url`, `cta_secondary_text`, `cta_secondary_url`, `show_adoption`, `adoption_mode`, `show_products`, `products_mode`, `show_testimonials`, `updated_at`) VALUES
(1, 'Your pet’s health, organized & care made simple.', 'Owners, Vets & Shelters: appointments, records, adoption, and curated products.', NULL, NULL, NULL, NULL, NULL, 1, 'latest', 1, 'latest', 1, '2025-09-09 19:33:58');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `vet_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `type` varchar(60) DEFAULT NULL,
  `title` varchar(180) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter`
--

CREATE TABLE `newsletter` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `body` text DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('placed','packed','shipped','delivered','cancelled','refunded') DEFAULT 'placed',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) GENERATED ALWAYS AS (`subtotal` + `shipping` - `discount`) STORED,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) GENERATED ALWAYS AS (`qty` * `unit_price`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `owners`
--

CREATE TABLE `owners` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `adopt_interest` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owners`
--

INSERT INTO `owners` (`user_id`, `full_name`, `email`, `phone`, `address`, `city`, `country`, `adopt_interest`, `created_at`, `updated_at`) VALUES
(39, 'anas', 'sham@gmail.com', '03312217600', NULL, 'Karachi', 'Pakistan', 1, '2025-09-13 06:10:09', NULL),
(42, 'Demo', 'demo123@gmail.com', '0315674893', NULL, 'Karachi', 'Pakistan', 1, '2025-09-13 07:42:50', NULL),
(46, 'Demo3', 'demo3@gmail.com', '30156396397', NULL, 'Islamabad', 'Pakistan', 1, '2025-09-13 08:30:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `status` enum('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
  `method` varchar(30) DEFAULT NULL,
  `receipt` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `species` varchar(40) DEFAULT NULL,
  `breed` varchar(80) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('Male','Female','Unknown') DEFAULT 'Unknown',
  `avatar` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image_alt` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `urgent_until` datetime DEFAULT NULL,
  `spotlight` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('available','adopted','pending') DEFAULT 'available',
  `city` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`id`, `user_id`, `name`, `species`, `breed`, `age`, `gender`, `avatar`, `image`, `category`, `image_alt`, `description`, `featured`, `urgent_until`, `spotlight`, `status`, `city`, `created_at`, `updated_at`) VALUES
(11, 8, 'anas', 'DOG', 'Persian', 7, 'Female', NULL, 'pet_20250911_114827_509fa147.jpeg', NULL, NULL, '{\"age_unit\":\"y\",\"next_vaccine\":\"2025\\/12\\/12\",\"allergies\":\"ASDASDASA\",\"vaccinated\":1,\"notes\":\"nm  b\"}', 0, NULL, 0, 'available', NULL, '2025-09-11 09:48:27', '2025-09-11 09:48:27'),
(19, 42, 'Glory', 'Cat', 'Persian', 2, 'Female', NULL, 'pet_20250913_094458_ac4594e2.jpeg', NULL, NULL, '{\"age_unit\":\"y\",\"next_vaccine\":\"2026-1-5\",\"allergies\":\"Chicken\",\"vaccinated\":1,\"notes\":\"Sapying\"}', 0, NULL, 0, 'available', NULL, '2025-09-13 07:44:58', '2025-09-13 07:44:58'),
(20, 46, 'Snow', 'Cat', 'Persian', 2, 'Female', NULL, 'pet_20250913_103114_17b8a583.jpeg', NULL, NULL, '{\"age_unit\":\"y\",\"next_vaccine\":\"2025-12-5\",\"allergies\":\"Chicken\",\"vaccinated\":1,\"notes\":\"\"}', 0, NULL, 0, 'available', NULL, '2025-09-13 08:31:14', '2025-09-13 08:31:14');

-- --------------------------------------------------------

--
-- Table structure for table `pet_allergies`
--

CREATE TABLE `pet_allergies` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `allergen` varchar(120) NOT NULL,
  `severity` enum('low','medium','high') DEFAULT 'low',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pet_files`
--

CREATE TABLE `pet_files` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `file_type` enum('report','certificate','xray','prescription','other') DEFAULT 'other',
  `file_path` varchar(255) NOT NULL,
  `title` varchar(160) DEFAULT NULL,
  `issued_by` varchar(160) DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pet_vaccinations`
--

CREATE TABLE `pet_vaccinations` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `vaccine_name` varchar(120) NOT NULL,
  `due_date` date DEFAULT NULL,
  `given_date` date DEFAULT NULL,
  `vet_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_qty` int(11) DEFAULT 0,
  `image` varchar(120) DEFAULT NULL,
  `image_alt` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `stock_qty`, `image`, `image_alt`, `description`, `featured`) VALUES
(1, 'Premium Dog Food', 2500.00, 20, 'uploads/products/prod_20250913_074001_6bab222abeb2.jpg', NULL, 'High-protein chicken formula for active dogs.', 1),
(2, 'Cat Scratching Post', 1800.00, 15, 'uploads/products/prod_20250913_073824_8dda4d092f23.webp', NULL, 'Durable sisal rope scratching post for indoor cats.', 1),
(4, 'Whishkas Treat', 200.00, 10, 'uploads/products/prod_20250912_135743_cd0fafd3d131.webp', NULL, 'Good for cats', 1),
(5, 'Pet Feeding kit', 400.00, 300, 'uploads/products/prod_20250913_090645_929e83af128b.webp', NULL, 'A wonderful feeders’ kit to give their dog or cat nutrient-containing liquids or medication. In the treatment of sick patients or supplementing routine diet, feeding kits have greatly made fluid administration a lot easier as the liquid is administered painlessly in an efficient manner without any injury either to the animal or the owner. All these liquids, and especially when there is the involvement of illness or recovery, are really hard to administer or provide without proper equipment.', 0),
(6, 'Adjustable Bell Collar', 300.00, 400, 'uploads/products/prod_20250913_090805_a527921f906d.webp', NULL, '', 0),
(7, 'Maybel Elite Premium Cat Food', 0.00, 300, 'uploads/products/prod_20250913_090940_5595640eca11.webp', NULL, 'We are told that Maybel Elite is Pakistan’s Best Cat Food by experts, vets, and other cat owners. And there are solid reasons for that, the first being the immediate health benefits for cat owners. Elite has been developed by experts for four years by people who were unable to find optimal food for their cats. Options available in the market are either unhealthy or overpriced. And there is always some degree of pet food availability issue. Maybel’s Elite also offers cat owners the best of both worlds – affordability and health benefits. So if you are a cat owner, there’s nothing else like Elite.', 0),
(8, 'Pedigree Wet Dog Food', 220.00, 34, 'uploads/products/prod_20250913_091044_194d096ef18c.webp', NULL, 'Each pouch is packed with essential nutrients designed to support your dog’s overall health and vitality. With a carefully formulated recipe, you can rest assured that your furry companion is getting the balanced diet they deserve.', 0),
(9, 'Reflex Puppy Food Lamb and Rice', 343.00, 500, 'uploads/products/prod_20250913_091207_e6d4c9bc5730.webp', NULL, 'Each pouch is packed with essential nutrients designed to support your dog’s overall health and vitality. With a carefully formulated recipe, you can rest assured that your furry companion is getting the balanced diet they deserve.', 0);

-- --------------------------------------------------------

--
-- Table structure for table `request`
--

CREATE TABLE `request` (
  `id` int(11) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) NOT NULL,
  `pet_name` varchar(120) NOT NULL,
  `breed` varchar(120) DEFAULT '',
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `score` decimal(4,1) DEFAULT NULL,
  `status` enum('pending','pre-approved','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `shelter_id` int(11) DEFAULT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `addoption_id` int(11) DEFAULT NULL,
  `pet_source` enum('pets','addoption') NOT NULL DEFAULT 'addoption',
  `owner_id` int(11) DEFAULT NULL,
  `applicant_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request`
--

INSERT INTO `request` (`id`, `applicant_name`, `email`, `phone`, `pet_name`, `breed`, `request_date`, `score`, `status`, `notes`, `shelter_id`, `pet_id`, `addoption_id`, `pet_source`, `owner_id`, `applicant_id`, `message`, `experience`, `created_at`, `updated_at`) VALUES
(4, '', '', '30274917493', '', '', '2025-09-13 12:46:39', NULL, 'pending', NULL, 36, NULL, 14, 'addoption', NULL, 42, 'can i have more info ?', '', '2025-09-13 12:46:39', '2025-09-13 12:46:39'),
(5, '', '', '+923352176482', '', '', '2025-09-13 12:47:59', NULL, 'pending', NULL, 36, NULL, 11, 'addoption', NULL, 42, 'can i have more info ?', '', '2025-09-13 12:47:59', '2025-09-13 12:47:59');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `vet_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shelters`
--

CREATE TABLE `shelters` (
  `user_id` int(11) NOT NULL,
  `shelter_name` varchar(150) DEFAULT NULL,
  `reg_no` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `logo_image` varchar(255) DEFAULT NULL,
  `reg_doc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shelters`
--

INSERT INTO `shelters` (`user_id`, `shelter_name`, `reg_no`, `capacity`, `address`, `city`, `country`, `logo_image`, `reg_doc`) VALUES
(36, 'ahtisham spport', '12345123456767', 23, 'houser', 'Karachi', 'Pakistan', 'uploads/shelters/logo_36.jpg', 'uploads/shelters/doc_36.pdf'),
(44, 'Pets Health 2', 'PV-428472947202', 70, 'Dallas', 'Chicago', 'United States', 'uploads/shelters/logo_44.jpg', 'uploads/shelters/doc_44.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `stories`
--

CREATE TABLE `stories` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `summary` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `role` enum('owner','vet','shelter') NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `message` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `name`, `role`, `avatar`, `rating`, `message`, `is_active`, `created_at`) VALUES
(1, 'Ayesha', 'owner', '/uploads/testimonials/t-e39f4110.jpg', 5, 'FurShield made managing Buddy’s vaccinations so easy!', 1, '2025-09-09 19:38:17'),
(2, 'Dr. Imran', 'vet', '/uploads/testimonials/t-42c582f8.jpg', 4, 'The system helps me track appointments & history quickly.', 1, '2025-09-09 19:38:17'),
(3, 'Happy Paws Shelter', 'shelter', '/uploads/testimonials/t-cd4e7037.jpg', 5, 'Adoption requests are streamlined, less paperwork!', 1, '2025-09-09 19:38:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('owner','vet','shelter','admin') NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `status` enum('pending','active','inactive') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `image` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `name`, `email`, `phone`, `pass_hash`, `status`, `created_at`, `approved_at`, `image`) VALUES
(8, 'admin', 'Admin', 'admin@furshield.com', NULL, '$2y$10$dR14ppztN1x9tkzerOtOwuJd67UEQnDp4ThWwiVWdFmlNPcdHC4XO', 'active', '2025-09-10 15:59:31', NULL, NULL),
(36, 'shelter', 'anas', 'Ahtisham@gmail.com', '331 2217600', '$2y$10$CyTmUpRm7I4Aq7akG4GBAeW4gvnEkT2gTy0FspkEinGikH1qdNnLy', 'active', '2025-09-13 03:17:26', '2025-09-13 03:18:26', NULL),
(37, 'vet', 'asians', 'syedpardasi.2@gmail.com', '331 2217600', '$2y$10$cFuW3a6Hfrvi4h3/lq9rp.4s4DmBIQw1mcM0v30hpLTFCiSbdQslC', 'active', '2025-09-13 03:24:39', '2025-09-13 03:25:29', NULL),
(39, 'owner', 'anas', 'sham@gmail.com', '03312217600', '$2y$10$acmcWa1VZquhamOUQe/nQeWE2Oei.8mse55OokycAFhhwPp4a78fm', 'active', '2025-09-13 06:10:09', NULL, NULL),
(40, 'vet', 'anas', 'anas@gmail.com', '331 2217600', '$2y$10$TVmmdy7qMu0ObMbW/.Aorej8j7m7dJtUhmyAUTF2d8qbK5tyRtCjy', 'active', '2025-09-13 07:19:25', '2025-09-13 08:40:50', NULL),
(41, 'vet', 'asim', 'asim@example.com', '03312217600', '$2y$10$ZD/zh9ipUBv9Ya0kR9CnzOxl8Y1kCdAX37eeC.Sg3oklHdAypvVLC', 'active', '2025-09-13 07:21:02', '2025-09-13 08:40:48', NULL),
(42, 'owner', 'Demo', 'demo123@gmail.com', '0315674893', '$2y$10$TQKo8MccRjrOLdIZ6XrvwOLuYUhTQZZo3E.asAMCRrCr2zxL0iLW6', 'active', '2025-09-13 07:42:50', NULL, 'uploads/avatars/ava_20250913_094331_ba4bcebe.jpg'),
(43, 'vet', 'Dr.Aliza', 'aliza786@gmail.com', '301567483', '$2y$10$idsDTcwqWzsCdLShQMwUbeEnrYPp.2nIvEZ79jc8P.aqNhYLd1wA6', 'active', '2025-09-13 07:49:42', '2025-09-13 07:50:55', NULL),
(44, 'shelter', 'Dahim', 'dahim786@gmail.com', '9247247397', '$2y$10$rh9cT3YeZRsTOTRxzEAGl.gagq2NtpzNT45ZvJfIE/1uACRbBGx4.', 'active', '2025-09-13 07:54:56', '2025-09-13 07:55:34', NULL),
(45, 'vet', 'Demo2', 'demo2@gmail.com', '3014659204', '$2y$10$er824ObGXFA72xFPrm/8nupXB/6Gd3cduw5TzFSwyc3gSD85ZJrSO', 'active', '2025-09-13 08:28:09', '2025-09-13 08:29:13', NULL),
(46, 'owner', 'Demo3', 'demo3@gmail.com', '30156396397', '$2y$10$N16o6lJTzCMi7cG0NdyI9OjyF5RoB5uxzARsL1vQ2A4Pd/bohCDfO', 'active', '2025-09-13 08:30:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_prefs`
--

CREATE TABLE `user_prefs` (
  `user_id` int(11) NOT NULL,
  `email_notif` tinyint(1) NOT NULL DEFAULT 1,
  `sms_notif` tinyint(1) NOT NULL DEFAULT 0,
  `adoption_visibility_city` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vets`
--

CREATE TABLE `vets` (
  `user_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `license_no` varchar(100) DEFAULT NULL,
  `cnic_image` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `slots_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slots_json`)),
  `clinic_address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `license_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vets`
--

INSERT INTO `vets` (`user_id`, `name`, `specialization`, `experience_years`, `license_no`, `cnic_image`, `profile_image`, `slots_json`, `clinic_address`, `city`, `country`, `license_image`) VALUES
(37, NULL, 'jkslcascjbaj', 12, NULL, 'uploads/vets/cnic_37.jpg', 'uploads/vets/profile_37.jpg', NULL, '12345123456789', 'Karachi', 'Pakistan', NULL),
(40, NULL, 'FurrEver Vets Animal Care', 23, NULL, 'uploads/vets/cnic_40.jpg', 'uploads/vets/profile_40.jpg', NULL, 'houser', 'Karachi', 'Pakistan', NULL),
(41, NULL, 'Asim Vets Animal Care', 34, NULL, 'uploads/vets/cnic_41.jpg', 'uploads/vets/profile_41.jpg', NULL, 'houser', 'Karachi', 'Pakistan', NULL),
(43, NULL, 'Vaccine', 4, NULL, 'uploads/vets/cnic_43.jpg', 'uploads/vets/profile_43.jpg', NULL, 'golmarket', 'Lahore', 'Pakistan', NULL),
(45, NULL, 'Surgery', 6, NULL, 'uploads/vets/cnic_45.jpg', 'uploads/vets/profile_45.jpg', NULL, 'golmarket', 'Lahore', 'Pakistan', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vet_availability`
--

CREATE TABLE `vet_availability` (
  `id` int(11) NOT NULL,
  `vet_id` int(11) NOT NULL,
  `dow` tinyint(4) DEFAULT NULL,
  `specific_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vet_availability`
--

INSERT INTO `vet_availability` (`id`, `vet_id`, `dow`, `specific_date`, `start_time`, `end_time`, `is_active`, `created_at`) VALUES
(1, 33, 0, NULL, '13:00:00', '17:00:00', 1, '2025-09-11 20:12:46'),
(3, 33, 1, NULL, '09:00:00', '18:00:00', 1, '2025-09-11 20:17:32'),
(4, 33, 2, NULL, '09:00:00', '17:00:00', 1, '2025-09-11 20:17:51'),
(5, 33, 3, NULL, '09:00:00', '17:00:00', 1, '2025-09-11 20:18:05'),
(6, 33, 4, NULL, '09:00:00', '17:00:00', 1, '2025-09-11 20:18:19'),
(7, 33, 5, NULL, '09:00:00', '17:00:00', 1, '2025-09-11 20:19:23'),
(8, 33, 6, NULL, '09:00:00', '18:00:00', 1, '2025-09-11 20:19:36'),
(9, 33, NULL, '2025-09-20', '14:19:00', '15:00:00', 1, '2025-09-11 20:20:03'),
(10, 33, NULL, '2025-09-20', '14:19:00', '15:00:00', 1, '2025-09-11 20:27:44'),
(11, 43, 0, NULL, '10:00:00', '13:00:00', 1, '2025-09-13 07:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `vet_reviews`
--

CREATE TABLE `vet_reviews` (
  `id` int(11) NOT NULL,
  `owner_user_id` int(11) NOT NULL,
  `vet_user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_addoption_with_shelter`
-- (See below for the actual view)
--
CREATE TABLE `v_addoption_with_shelter` (
`id` int(11)
,`created_by` int(11)
,`shelter_id` int(11)
,`name` varchar(100)
,`species` varchar(50)
,`breed` varchar(100)
,`age` varchar(50)
,`gender` enum('Male','Female')
,`avatar` varchar(255)
,`image` varchar(255)
,`category` varchar(100)
,`image_alt` varchar(150)
,`description` text
,`featured` tinyint(1)
,`urgent_until` date
,`spotlight` tinyint(1)
,`status` enum('available','pending','adopted')
,`hold_until` datetime
,`city` varchar(100)
,`created_at` timestamp
,`updated_at` timestamp
,`shelter_name` varchar(100)
,`shelter_email` varchar(120)
);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(1, 1, 3, '2025-09-10 11:09:36'),
(4, 34, 4, '2025-09-12 21:10:28');

-- --------------------------------------------------------

--
-- Structure for view `v_addoption_with_shelter`
--
DROP TABLE IF EXISTS `v_addoption_with_shelter`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_addoption_with_shelter`  AS SELECT `ao`.`id` AS `id`, `ao`.`created_by` AS `created_by`, `ao`.`shelter_id` AS `shelter_id`, `ao`.`name` AS `name`, `ao`.`species` AS `species`, `ao`.`breed` AS `breed`, `ao`.`age` AS `age`, `ao`.`gender` AS `gender`, `ao`.`avatar` AS `avatar`, `ao`.`image` AS `image`, `ao`.`category` AS `category`, `ao`.`image_alt` AS `image_alt`, `ao`.`description` AS `description`, `ao`.`featured` AS `featured`, `ao`.`urgent_until` AS `urgent_until`, `ao`.`spotlight` AS `spotlight`, `ao`.`status` AS `status`, `ao`.`hold_until` AS `hold_until`, `ao`.`city` AS `city`, `ao`.`created_at` AS `created_at`, `ao`.`updated_at` AS `updated_at`, `u`.`name` AS `shelter_name`, `u`.`email` AS `shelter_email` FROM (`addoption` `ao` left join `users` `u` on(`u`.`id` = `ao`.`shelter_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addoption`
--
ALTER TABLE `addoption`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_addoption_user` (`created_by`),
  ADD KEY `idx_addoption_status` (`status`),
  ADD KEY `idx_addoption_user` (`created_by`),
  ADD KEY `idx_addoption_hold` (`hold_until`),
  ADD KEY `idx_addoption_shelter` (`shelter_id`);

--
-- Indexes for table `adoption_agreements`
--
ALTER TABLE `adoption_agreements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_agree_app` (`application_id`);

--
-- Indexes for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_app_pet` (`pet_id`),
  ADD KEY `idx_app_shelter` (`shelter_id`),
  ADD KEY `idx_app_owner` (`owner_id`),
  ADD KEY `idx_app_status` (`status`),
  ADD KEY `idx_app_created` (`created_at`);

--
-- Indexes for table `adoption_meetings`
--
ALTER TABLE `adoption_meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meet_app` (`application_id`),
  ADD KEY `idx_meet_shelter` (`shelter_id`),
  ADD KEY `idx_meet_owner` (`owner_id`);

--
-- Indexes for table `adoption_requests`
--
ALTER TABLE `adoption_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`),
  ADD KEY `adopter_user_id` (`adopter_user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_adopt_status` (`status`,`pet_id`,`adopter_user_id`);

--
-- Indexes for table `application_events`
--
ALTER TABLE `application_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_evt_app` (`application_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_appt_pet` (`pet_id`),
  ADD KEY `fk_appt_owner` (`owner_id`),
  ADD KEY `fk_appt_vet` (`vet_id`),
  ADD KEY `idx_appt_vet` (`vet_id`),
  ADD KEY `idx_appt_sched` (`scheduled_at`),
  ADD KEY `idx_appt_owner` (`owner_id`),
  ADD KEY `idx_appt_pet` (`pet_id`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_blogs_active` (`is_active`,`id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_date` (`date`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_faqs_active` (`is_active`,`is_top`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hr_owner_date` (`owner_id`,`record_date`),
  ADD KEY `idx_hr_pet` (`pet_id`);

--
-- Indexes for table `homepage_settings`
--
ALTER TABLE `homepage_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `newsletter`
--
ALTER TABLE `newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_user` (`user_id`,`status`,`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `owners`
--
ALTER TABLE `owners`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pay_app` (`application_id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pets_owner` (`user_id`),
  ADD KEY `idx_pets_filters` (`status`,`species`,`city`);

--
-- Indexes for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `pet_files`
--
ALTER TABLE `pet_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `pet_vaccinations`
--
ALTER TABLE `pet_vaccinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`),
  ADD KEY `vet_id` (`vet_id`),
  ADD KEY `idx_vacc_due` (`due_date`,`given_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `request`
--
ALTER TABLE `request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_request_date` (`request_date`),
  ADD KEY `idx_shelter` (`shelter_id`),
  ADD KEY `idx_pet` (`pet_id`),
  ADD KEY `idx_owner` (`owner_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vet_id` (`vet_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `shelters`
--
ALTER TABLE `shelters`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stories_pet` (`pet_id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_testi_active` (`is_active`,`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `user_prefs`
--
ALTER TABLE `user_prefs`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `vets`
--
ALTER TABLE `vets`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `vet_availability`
--
ALTER TABLE `vet_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vet_id` (`vet_id`),
  ADD KEY `dow` (`dow`),
  ADD KEY `specific_date` (`specific_date`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `vet_reviews`
--
ALTER TABLE `vet_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_user_id` (`owner_user_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_vet_reviews` (`vet_user_id`,`rating`,`created_at`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addoption`
--
ALTER TABLE `addoption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `adoption_agreements`
--
ALTER TABLE `adoption_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `adoption_meetings`
--
ALTER TABLE `adoption_meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `adoption_requests`
--
ALTER TABLE `adoption_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_events`
--
ALTER TABLE `application_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pet_files`
--
ALTER TABLE `pet_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pet_vaccinations`
--
ALTER TABLE `pet_vaccinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `request`
--
ALTER TABLE `request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `vet_availability`
--
ALTER TABLE `vet_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `vet_reviews`
--
ALTER TABLE `vet_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addoption`
--
ALTER TABLE `addoption`
  ADD CONSTRAINT `fk_addoption_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_addoption_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `adoption_agreements`
--
ALTER TABLE `adoption_agreements`
  ADD CONSTRAINT `fk_agree_app` FOREIGN KEY (`application_id`) REFERENCES `adoption_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  ADD CONSTRAINT `fk_app_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_app_pet` FOREIGN KEY (`pet_id`) REFERENCES `addoption` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_app_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `adoption_meetings`
--
ALTER TABLE `adoption_meetings`
  ADD CONSTRAINT `fk_meet_app` FOREIGN KEY (`application_id`) REFERENCES `adoption_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `adoption_requests`
--
ALTER TABLE `adoption_requests`
  ADD CONSTRAINT `adoption_requests_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `adoption_requests_ibfk_2` FOREIGN KEY (`adopter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `adoption_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `application_events`
--
ALTER TABLE `application_events`
  ADD CONSTRAINT `fk_evt_app` FOREIGN KEY (`application_id`) REFERENCES `adoption_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appt_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appt_vet` FOREIGN KEY (`vet_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `fk_hr_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `owners`
--
ALTER TABLE `owners`
  ADD CONSTRAINT `owners_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_app` FOREIGN KEY (`application_id`) REFERENCES `adoption_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `fk_pets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  ADD CONSTRAINT `pet_allergies_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_files`
--
ALTER TABLE `pet_files`
  ADD CONSTRAINT `pet_files_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_vaccinations`
--
ALTER TABLE `pet_vaccinations`
  ADD CONSTRAINT `pet_vaccinations_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pet_vaccinations_ibfk_2` FOREIGN KEY (`vet_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request`
--
ALTER TABLE `request`
  ADD CONSTRAINT `fk_request_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`vet_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shelters`
--
ALTER TABLE `shelters`
  ADD CONSTRAINT `shelters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `fk_stories_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_prefs`
--
ALTER TABLE `user_prefs`
  ADD CONSTRAINT `user_prefs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vets`
--
ALTER TABLE `vets`
  ADD CONSTRAINT `vets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vet_reviews`
--
ALTER TABLE `vet_reviews`
  ADD CONSTRAINT `vet_reviews_ibfk_1` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vet_reviews_ibfk_2` FOREIGN KEY (`vet_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vet_reviews_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
