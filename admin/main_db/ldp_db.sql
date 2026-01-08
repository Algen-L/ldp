-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 08, 2026 at 07:17 AM
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
-- Database: `ldp_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'Logged In', NULL, '::1', '2026-01-08 02:00:21'),
(2, 4, 'Logged In', NULL, '::1', '2026-01-08 02:32:11'),
(3, 1, 'Logged In', NULL, '::1', '2026-01-08 02:32:31'),
(4, 6, 'Logged In', NULL, '::1', '2026-01-08 02:50:58'),
(5, 1, 'Logged In', NULL, '::1', '2026-01-08 02:52:17'),
(6, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 03:05:47'),
(7, 1, 'Logged Out', NULL, '::1', '2026-01-08 03:05:50'),
(8, 1, 'Logged In', NULL, '::1', '2026-01-08 03:05:55'),
(9, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 03:05:55'),
(10, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 03:47:44'),
(11, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 03:47:46'),
(12, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 03:47:51'),
(13, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 03:48:05'),
(14, 1, 'Viewed Specific Activity', 'escal', '::1', '2026-01-08 03:48:07'),
(15, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 03:50:53'),
(16, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 04:26:10'),
(17, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 04:26:12'),
(18, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 04:26:17'),
(19, 1, 'Logged Out', NULL, '::1', '2026-01-08 04:26:58'),
(20, 4, 'Logged In', NULL, '::1', '2026-01-08 04:27:07'),
(21, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:27:07'),
(22, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:27:12'),
(23, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:34:07'),
(24, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:34:16'),
(25, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:40:28'),
(26, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:40:36'),
(27, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:40:40'),
(28, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:40:42'),
(29, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:40:45'),
(30, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:40:54'),
(31, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:53:27'),
(32, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:54:05'),
(33, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:55:37'),
(34, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:58:46'),
(35, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 04:58:47'),
(36, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:02:14'),
(37, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:02:14'),
(38, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:02:17'),
(39, 4, 'Logged Out', NULL, '::1', '2026-01-08 05:02:19'),
(40, 1, 'Logged In', NULL, '::1', '2026-01-08 05:02:24'),
(41, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:02:24'),
(42, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:03:04'),
(43, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:06:39'),
(44, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:10:12'),
(45, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:10:13'),
(46, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:10:17'),
(47, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:10:43'),
(48, 1, 'Logged Out', NULL, '::1', '2026-01-08 05:10:44'),
(49, 4, 'Logged In', NULL, '::1', '2026-01-08 05:10:51'),
(50, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:10:51'),
(51, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:10:56'),
(52, 4, 'Logged Out', NULL, '::1', '2026-01-08 05:10:59'),
(53, 1, 'Logged In', NULL, '::1', '2026-01-08 05:11:03'),
(54, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:11:03'),
(55, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:11:06'),
(56, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:11:09'),
(57, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:14:44'),
(58, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:15:16'),
(59, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:15:20'),
(60, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:17:12'),
(61, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:19:20'),
(62, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:21:39'),
(63, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:21:40'),
(64, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:21:54'),
(65, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:21:55'),
(66, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:21:59'),
(67, 1, 'Logged Out', NULL, '::1', '2026-01-08 05:22:21'),
(68, 4, 'Logged In', NULL, '::1', '2026-01-08 05:22:27'),
(69, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:22:27'),
(70, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:26:08'),
(71, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:28:19'),
(72, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:28:20'),
(73, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:28:53'),
(74, 4, 'Viewed User Dashboard', NULL, '::1', '2026-01-08 05:28:54'),
(75, 4, 'Logged Out', NULL, '::1', '2026-01-08 05:28:57'),
(76, 1, 'Logged In', NULL, '::1', '2026-01-08 05:29:04'),
(77, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:29:04'),
(78, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:29:25'),
(79, 1, 'Viewed Admin Dashboard', NULL, '::1', '2026-01-08 05:32:36'),
(80, 1, 'Logged Out', NULL, '::1', '2026-01-08 05:37:35'),
(81, 4, 'Logged In', NULL, '::1', '2026-01-08 05:37:41'),
(82, 4, 'Logged Out', NULL, '::1', '2026-01-08 05:38:25'),
(83, 1, 'Logged In', NULL, '::1', '2026-01-08 05:40:25'),
(84, 1, 'Viewed Specific Activity', 'escal', '::1', '2026-01-08 05:40:47'),
(85, 1, 'Viewed Specific Activity', 'PROCEEDING TO DESIGN', '::1', '2026-01-08 05:40:55'),
(86, 1, 'Logged Out', NULL, '::1', '2026-01-08 05:41:24'),
(87, 4, 'Logged In', NULL, '::1', '2026-01-08 05:41:34'),
(88, 4, 'Logged Out', NULL, '::1', '2026-01-08 05:43:26'),
(89, 1, 'Logged In', NULL, '::1', '2026-01-08 05:43:30'),
(90, 1, 'Logged Out', NULL, '::1', '2026-01-08 05:44:19'),
(91, 4, 'Logged In', NULL, '::1', '2026-01-08 05:44:26'),
(92, 4, 'Submitted Activity', 'Activity Title: Natapon', '::1', '2026-01-08 05:46:12'),
(93, 4, 'Submitted Activity', 'Activity Title: pushpush', '::1', '2026-01-08 05:49:42');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `status` varchar(50) DEFAULT 'Attended'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `user_id`, `event_name`, `event_date`, `status`) VALUES
(1, 1, 'Python Workshop', '2023-10-15', 'Attended'),
(2, 1, 'Agile Leadership', '2023-11-05', 'Attended'),
(3, 1, 'Cybersecurity Basics', '2023-12-12', 'Completed'),
(4, 1, 'Data Science Intro', '2024-01-20', 'Registered');

-- --------------------------------------------------------

--
-- Table structure for table `ld_activities`
--

CREATE TABLE `ld_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `date_attended` date DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `modality` varchar(100) DEFAULT NULL,
  `competency` varchar(255) DEFAULT NULL,
  `type_ld` varchar(100) DEFAULT NULL,
  `type_ld_others` varchar(255) DEFAULT NULL,
  `conducted_by` varchar(255) DEFAULT NULL,
  `organizer_signature_path` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `workplace_application` text DEFAULT NULL,
  `workplace_image_path` varchar(255) DEFAULT NULL,
  `reflection` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ld_activities`
--

INSERT INTO `ld_activities` (`id`, `user_id`, `title`, `date_attended`, `venue`, `modality`, `competency`, `type_ld`, `type_ld_others`, `conducted_by`, `organizer_signature_path`, `approved_by`, `workplace_application`, `workplace_image_path`, `reflection`, `status`, `signature_path`, `created_at`) VALUES
(1, 1, 'Advanced Project Management', '2023-11-20', 'Manila Conference Center', 'Formal Training', 'Leadership', 'Managerial', NULL, 'PMI Philippines', NULL, 'Director Smith', NULL, NULL, NULL, 'Pending', NULL, '2026-01-06 05:46:51'),
(2, 1, 'Web Security Fundamentals', '2023-12-05', 'Online Zoom', 'Job-Embedded Learning', 'Technical Skills', 'Technical', NULL, 'CyberSec Inc', NULL, 'IT Head Jones', NULL, NULL, NULL, 'Approved', NULL, '2026-01-06 05:46:51'),
(3, 3, 'seminar', '2026-01-14', 'pacita astrodome', 'Relationship Discussion Learning', 'asdqewqeqw', 'Supervisory', NULL, 'saddqwewqe', NULL, 'asdwqeqwe', 'sdasdsadcweqe', NULL, 'sadasdqweqwvasdwqeasd', 'Pending', NULL, '2026-01-06 05:49:10'),
(4, 3, 'test1', '2026-01-06', 'pacita astrodome', 'Formal Training', 'asdqewqeqw', 'Technical', NULL, 'sdasdqwewqewqe', NULL, 'Algenloveres', 'basta ikaw lang', NULL, 'hindi na pala ikaw', 'Pending', 'uploads/signatures/695cafc319052_signature.png', '2026-01-06 06:46:27'),
(5, 4, 'MLBB Tournament', '2026-01-20', 'San Pedro CIty SM', 'Learning Action Cell', 'U.B San Pedro', 'Technical', NULL, 'Orcalelo', NULL, 'Tralelo', 'Play MLBB Forever', NULL, 'Ikaw ang repleksyon na sumasalamin', 'Pending', 'uploads/signatures/695d193d9015d_signature.png', '2026-01-06 14:16:29'),
(6, 4, 'SEMINAR NG MGA POGI', '2026-01-20', 'San Pedro CIty SM', 'Formal Training', 'SADASDQWEWQ', '', NULL, 'CEDDYBOI', 'uploads/signatures/695da5be09e9a_org_signature.png', 'CEDDY BOI', 'BASTA GANTO SIYA', NULL, 'POGI PALA KAMI', 'Pending', 'uploads/signatures/695da5be094d2_attest_signature.png', '2026-01-07 00:15:58'),
(7, 3, 'PRESENTATION OF L&D PASSBOOK', '2026-01-20', 'SDO', '', 'SYSTEM AUTOMATION', 'Technical', NULL, 'SIR JB', 'uploads/signatures/695dacf42c12e_org_signature.png', 'CEDDYBOI', 'SHEEEEEESSSHHHT', NULL, 'SALAMIN', 'Pending', 'uploads/signatures/695dacf42bc14_attest_testt.jpg', '2026-01-07 00:46:44'),
(8, 4, 'Unpre', '1993-09-12', 'Duon', '', 'Ok', 'Others', 'Superman', 'Show', 'uploads/signatures/695dc7ee55d16_org_signature.png', '', 'admn', NULL, 'Mirror', 'Pending', 'uploads/signatures/695dc7ee55915_attest_logo_sanpedro.png', '2026-01-07 02:41:50'),
(9, 4, 'PROCEEDING TO DESIGN', '2026-01-08', 'SDO', '', 'ADASDSADQWEWQ', 'Technical', '', 'GEN', 'uploads/signatures/695eec446006b_org_signature.png', '', 'tHE SADSDWQESADSDQWEASDWWEQWASDWQE', 'uploads/workplace/695eec446102c_work_607959178_757730910682183_9194824805545449197_n.jpg', 'SADFASDQEQWEASDSADQWEASDSADCAS', 'Pending', '', '2026-01-07 23:29:08'),
(10, 4, 'PROCEEDING TO DESIGN', '2026-01-08', 'SDO', '', 'ADASDSADQWEWQ', 'Technical', '', 'GEN', 'uploads/signatures/695eed9b16c50_org_signature.png', '', 'tHE SADSDWQESADSDQWEASDWWEQWASDWQE', 'uploads/workplace/695eed9b17171_work_607959178_757730910682183_9194824805545449197_n.jpg', 'SADFASDQEQWEASDSADQWEASDSADCAS', 'Pending', '', '2026-01-07 23:34:51'),
(11, 4, 'escal', '2026-01-26', 'pacita astrodome', '', 'qweqwe', 'Supervisory', '', 'qewq', 'uploads/signatures/695f062a018d4_org_signature.png', 'Algenloveres', 'eto escall oh', 'uploads/workplace/695f062a01e9d_work_607768542_884539391179348_120388334797920829_n (1).jpg', 'qweqwasdsadqwewqesadd', 'Pending', 'uploads/signatures/695f062a013e7_attest_signature.png', '2026-01-08 01:19:38'),
(12, 4, 'Natapon', '2026-01-08', 'SDO', '', 'superfast stirring', 'Others', '', 'cedyboi', 'uploads/signatures/695f44a4b316e_org_signature.png', 'Algenloveres', 'qwewqasdsadwqe', '', 'qwewqewqe', 'Pending', 'uploads/signatures/695f44a4b2dce_attest_signature.png', '2026-01-08 05:46:12'),
(13, 4, 'pushpush', '2026-01-30', 'sadsadqwe', '', 'U.B San Pedro', '', '', 'asqw', 'uploads/signatures/695f45762a89a_org_signature.png', '', 'aqwewqewqeasdwqe', '', 'qwewqerqwdsa', 'Pending', 'uploads/signatures/695f45762a3dc_attest_signature.png', '2026-01-08 05:49:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `office_station` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `rating_period` varchar(100) DEFAULT NULL,
  `area_of_specialization` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` varchar(20) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `office_station`, `position`, `rating_period`, `area_of_specialization`, `age`, `sex`, `role`, `created_at`, `profile_picture`) VALUES
(1, 'admin1', '$2y$10$KVmV0lPyAK1QmTU5Rf9tEOoDTtOv1ZkMJprI63N22.4DzobivzCaC', 'Administrator One', '', '', NULL, NULL, NULL, NULL, 'admin', '2026-01-06 05:05:27', NULL),
(2, 'admin2', '$2y$10$j3tCjdvvmQEW0IVs5rUTIuV0LsRJOzk1QkXn/ZfTxyeXyoSxMg2nK', 'Administrator Two', NULL, NULL, NULL, NULL, NULL, NULL, 'admin', '2026-01-06 05:05:27', NULL),
(3, 'Ced123', '$2y$10$ful.LEVU6N.Geqy.TH3Op.Pq32Qpp2tIr3m/ZqO6W8/vTc1k5lWVW', 'CED', 'ICT', 'IT2', '2025-2026', 'WEB DEV', 21, 'Male', 'user', '2026-01-06 05:06:49', 'uploads/profile_pics/695d9390aa5e6_Knight.jpg'),
(4, 'geodash', '$2y$10$oB5Bz9SD3QArP3uRcLMQTu8Bsw9G45d4Pfjwsx9.gydaUj2pNlh4m', 'Geo Dashi', 'ICT', 'IT HEAD', '2026', 'SOFTWARE DEV', 33, 'Male', 'user', '2026-01-06 11:42:47', 'uploads/profile_pics/695dc8980bc58_Whisk_beb4a30e2e7357184794c7dffa64f491eg.png'),
(5, 'shesh', '$2y$10$Y.NU8DgLlSeLHMVsrd8HQOF1qungu5QKpKVKstXjJEj/2kHIqBVIC', 'SHEESH', 'FINANCE (BUDGET)', 'SHOES', '2033', 'SOCKS', 22, 'Male', 'user', '2026-01-06 11:50:53', 'uploads/profile_pics/695cf80a27dc4_Knight.jpg'),
(6, 'super_admin1', '$2y$10$bOk3WaJeBkaomTLU2aSEr.Lz3rkp9wOk7QbZ7khbBW8zZAlqRKNIy', 'Super Admin One', 'Central Office', NULL, NULL, NULL, NULL, NULL, 'super_admin', '2026-01-08 02:40:45', NULL),
(7, 'super_admin2', '$2y$10$w5IJhwv.YF7qV5ZuzQKBnOi79YdIKk9.ElJIvc9rFwraD3H6hTbUW', 'Super Admin Two', 'Central Office', NULL, NULL, NULL, NULL, NULL, 'super_admin', '2026-01-08 02:40:45', NULL),
(8, 'super_admin3', '$2y$10$rkBTFcJaultB6GuNFGBZpOD19BA8XhjjitJDnKvWz1Q8T92XQ0wFS', 'Super Admin Three', 'Central Office', NULL, NULL, NULL, NULL, NULL, 'super_admin', '2026-01-08 02:40:45', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ld_activities`
--
ALTER TABLE `ld_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ld_activities`
--
ALTER TABLE `ld_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ld_activities`
--
ALTER TABLE `ld_activities`
  ADD CONSTRAINT `ld_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
