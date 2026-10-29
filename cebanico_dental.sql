-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2026 at 06:18 PM
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
-- Database: `cebanico_dental`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `service` varchar(100) NOT NULL,
  `status` enum('Pending','Confirmed','Completed','Cancelled','No Show') NOT NULL DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `request_id`, `appointment_date`, `day_of_week`, `service`, `status`, `remarks`, `created_at`, `updated_at`) VALUES
(6, 6, '2027-03-08', 'Monday', 'Braces Installation', 'Pending', NULL, '2026-10-29 17:00:57', '2026-10-29 17:00:57');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_requests`
--

CREATE TABLE `appointment_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `service` varchar(100) NOT NULL,
  `appointment_date` date NOT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment_requests`
--

INSERT INTO `appointment_requests` (`request_id`, `full_name`, `email`, `contact_number`, `service`, `appointment_date`, `consent`, `submitted_at`) VALUES
(6, 'Aron Mallari', 'aronmallari123@gmail.com', '09937696068', 'Braces Installation', '2027-03-08', 1, '2026-10-29 17:00:57');

-- --------------------------------------------------------

--
-- Stand-in structure for view `appointment_schedule`
-- (See below for the actual view)
--
CREATE TABLE `appointment_schedule` (
`appointment_id` int(10) unsigned
,`appointment_date` date
,`day_of_week` varchar(10)
,`service` varchar(100)
,`status` enum('Pending','Confirmed','Completed','Cancelled','No Show')
,`full_name` varchar(100)
,`email` varchar(150)
,`contact_number` varchar(20)
,`remarks` text
,`submitted_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `appointment_schedule`
--
DROP TABLE IF EXISTS `appointment_schedule`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `appointment_schedule`  AS SELECT `a`.`appointment_id` AS `appointment_id`, `a`.`appointment_date` AS `appointment_date`, `a`.`day_of_week` AS `day_of_week`, `a`.`service` AS `service`, `a`.`status` AS `status`, `r`.`full_name` AS `full_name`, `r`.`email` AS `email`, `r`.`contact_number` AS `contact_number`, `a`.`remarks` AS `remarks`, `r`.`submitted_at` AS `submitted_at` FROM (`appointments` `a` join `appointment_requests` `r` on(`r`.`request_id` = `a`.`request_id`)) ORDER BY `a`.`appointment_date` ASC, `a`.`appointment_id` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD UNIQUE KEY `uq_request` (`request_id`),
  ADD KEY `idx_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD PRIMARY KEY (`request_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_request` FOREIGN KEY (`request_id`) REFERENCES `appointment_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
