-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 12, 2026 at 12:57 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `csu_dorm_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(15) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `extension_name` varchar(10) DEFAULT NULL,
  `dob` date NOT NULL,
  `age` tinyint(3) UNSIGNED NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin','super_admin') NOT NULL DEFAULT 'user',
  `account_status` enum('pending','approved','blocked') NOT NULL DEFAULT 'approved',
  `privileges` text DEFAULT NULL,
  `purok_street` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city_municipality` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `date_registered` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `setup_completed` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `extension_name`, `dob`, `age`, `sex`, `email`, `username`, `password_hash`, `role`, `account_status`, `privileges`, `purok_street`, `barangay`, `city_municipality`, `province`, `country`, `zip_code`, `date_registered`, `profile_picture`, `setup_completed`) VALUES
(1, '2026-0001', 'Super Admin', NULL, 'Admin', NULL, '1990-01-01', 36, 'female', 'superadmin@csucc-ldms.local', 'super.admin', '$2y$10$gkCEHa80hHpCZONoNg31Iu.gq8ppTlxNpaIYOmzKWJKAXO63LqUhq', 'super_admin', 'approved', 'manage_accounts,manage_privileges,delete_accounts', 'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605', '2026-10-12 08:51:18', NULL, 1),
(2, '2026-0002', 'Administrator', NULL, 'Admin', NULL, '1990-01-01', 36, 'female', 'admin@csucc-ldms.local', 'system.admin', '$2y$10$lMhb/4L9PR9VaZeaw7TlAukkSTvfXJe46jG6T1CI/CVJ7QVQXE7SO', 'admin', 'approved', 'manage_registration,update_accounts', 'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605', '2026-10-12 08:51:18', NULL, 1),
(3, '2026-0003', 'Mickmick', 'Engalan', 'Paquio', '', '1990-01-01', 18, 'female', 'mickmickengalan123@gmail.com', 'mick.mick', '$2y$10$vV1U1FfAgtmv3SBncHn/x.NsT5A2.w1xkoWvOrP/EoSbo7bUt3RHW', 'super_admin', 'approved', 'manage_accounts,manage_privileges,delete_accounts', 'Purok-1', 'Marcos', 'Magallanes', 'Agusan Del Norte', 'Philippines', '8604', '2026-10-12 08:57:17', NULL, 1),
(4, '2026-0004', 'Louie', '', 'Sanrojo', '', '1990-01-01', 18, 'female', 'deobertaron.paquio@csucc.edu.ph', 'deobert.aron', '$2y$10$KH0Vrj0OUAy7L467e53utOiDW23wWDPjRwRkPI1gbpTcdPQ8hAVu.', 'admin', 'approved', 'manage_registration,update_accounts', 'Purok 9', 'Cali', 'Butuan City', 'Agusan Del Norte', 'Philippines', '8600', '2026-10-12 09:01:41', NULL, 1),
(5, '2026-0005', 'Micky', 'Cellos', 'Paquio', '', '1990-01-01', 18, 'female', 'mickoengalanpaquio@gmail.com', 'micko.engalan', '$2y$10$ZcwRwIyV/9JxMmPnFacdK.hI77EBd4svj99.bXaAe9tNYpLFbXA/.', 'admin', 'approved', 'manage_registration,update_accounts', 'Purok 9', 'Cali', 'Butuan City', 'Agusan Del Norte', 'Philippines', '8600', '2026-10-12 10:39:11', NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
