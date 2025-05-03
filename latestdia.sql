-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 03, 2025 at 04:30 PM
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
-- Database: `my_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_requests`
--

CREATE TABLE `sit_in_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lab_number` varchar(50) NOT NULL,
  `purpose` text NOT NULL,
  `status` enum('pending','approved','rejected','logged_out') DEFAULT 'pending',
  `feedback` text DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `pc_number` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0 COMMENT 'Flag to indicate if sit-in session is currently active',
  `start_time` datetime DEFAULT NULL COMMENT 'Time when the sit-in session started',
  `end_time` datetime DEFAULT NULL COMMENT 'Time when the sit-in session ended',
  `date_requested` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `FK_subject_id` (`subject_id`),
  ADD KEY `idx_pc_number` (`pc_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  ADD CONSTRAINT `FK_subject_id` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sit_in_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`idno`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
