-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 23, 2025 at 03:38 PM
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
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `date`) VALUES
(1, 'SHEEESHSHH', 'SHEEEESH', '2025-02-25'),
(3, 'UHAHAY', 'UHAHAY', '2025-03-23');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `is_active` tinyint(1) DEFAULT 0 COMMENT 'Flag to indicate if sit-in session is currently active',
  `start_time` datetime DEFAULT NULL COMMENT 'Time when the sit-in session started',
  `end_time` datetime DEFAULT NULL COMMENT 'Time when the sit-in session ended'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sit_in_requests`
--

INSERT INTO `sit_in_requests` (`id`, `student_id`, `lab_number`, `purpose`, `status`, `feedback`, `subject_id`, `is_active`, `start_time`, `end_time`) VALUES
(52, 912, '', 'Research', 'approved', NULL, 13, 0, '2025-03-22 22:47:26', '2025-03-22 23:09:30'),
(53, 20210920, '', 'Research', 'approved', NULL, 13, 0, '2025-03-22 23:15:07', '2025-03-22 23:18:16'),
(54, 20210920, '', 'Assignment', 'approved', NULL, 13, 0, '2025-03-22 23:34:16', '2025-03-22 23:40:47'),
(55, 20210920, '', 'Practice', 'approved', NULL, 14, 0, '2025-03-22 23:41:49', '2025-03-22 23:47:44'),
(56, 912, '', 'PHP', 'approved', NULL, 14, 0, '2025-03-22 23:48:03', '2025-03-23 19:27:30'),
(57, 20210920, '', 'Java', 'approved', 'SHEEESHEABLE (Rating: 5/5)', 14, 0, '2025-03-22 23:48:37', '2025-03-23 19:00:11'),
(58, 150, '', 'C#', 'approved', 'SHEEESH\r\n (Rating: 5/5)', 14, 0, '2025-03-23 19:26:39', '2025-03-23 19:27:01'),
(59, 22604004, '', 'PHP', 'approved', NULL, 13, 0, '2025-03-23 19:41:58', '2025-03-23 19:42:59'),
(60, 22604005, '', 'Python', 'approved', NULL, 14, 0, '2025-03-23 19:42:15', '2025-03-23 19:42:57'),
(61, 22604006, '', 'Java', 'approved', NULL, 13, 0, '2025-03-23 19:42:32', '2025-03-23 19:42:56'),
(62, 22604007, '', 'C#', 'approved', NULL, 13, 0, '2025-03-23 19:42:47', '2025-03-23 19:42:54'),
(63, 912, '', 'PHP', 'approved', NULL, 13, 0, '2025-03-23 20:24:17', '2025-03-23 21:03:44'),
(64, 912, '', 'ASP.NET', 'approved', NULL, 13, 0, '2025-03-23 22:29:59', '2025-03-23 22:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `lab_number` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `sessions` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `lab_number`, `date`, `start_time`, `end_time`, `sessions`, `status`) VALUES
(13, '   ', '528', '2025-03-26', '23:46:00', '12:47:00', 30, 'available'),
(14, '', '524', '2025-03-04', '14:41:00', '16:41:00', 30, 'available');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `idno` int(11) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `course` varchar(50) NOT NULL,
  `year` varchar(10) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT 'default.png',
  `role` enum('admin','student') NOT NULL,
  `remaining_sessions` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`idno`, `lastname`, `firstname`, `middlename`, `course`, `year`, `email`, `username`, `password_hash`, `photo`, `role`, `remaining_sessions`) VALUES
(1, '', '', NULL, '', '', '', 'admin', '$2y$10$0Pdod55WeBpTb8lfVyOsXeEkjAqVmaoeTlIshpefNaKS1tQatCMGa', 'default.png', 'admin', 120),
(150, 'lazaga', 'bayot', 'shode', 'BSIT', '3', 'bayotlazaga@gmail.com', 'bayot', '$2y$10$u30awug4SkYI0MA6k.836.fr5jhGspoxPI4z36Xws62MGRPbn2SjK', 'uploads/1742230771_diwata.jpg', 'student', 118),
(912, 'user2', 'user2', 'sheeesh', 'engineering', '4', 'user2@gmail.com', 'user2', '$2y$10$aKdWzKCW1IgOh.kq3UTpFOzTmD9hbsSN/1kTqjNl12U3m.wFkY4lu', 'uploads/1741753107_PENCIL.jpg', 'student', 112),
(20210920, 'Alonso', 'Zeke', 'Debulosan', 'computer_science', '1', 'zekealonso2021@gmail.com', 'zeke', '$2y$10$80CqHmO5U8SNLJWNQH1NWO4utz7iIJTeF3.LPxSqn/RmpmsAJsXuq', 'uploads/1742640573_zeke.jpg', 'student', 22),
(22604003, 'Alonso', 'Rolly', 'Cuestas', 'BSIT', '3', 'rollyalonso0912@gmail.com', 'mrpopoman12', '$2y$10$fUZuWW9uyLhSpmFOqaJXy.kVoUXsbfxvqkOURN8nweHmlQyfWJGY6', 'uploads/default.png', 'admin', 120),
(22604004, 'Doe', 'John', 'M', 'BSIT', '2', 'john.doe@example.com', 'johndoe', 'hashedpassword1', 'uploads/john.jpg', 'student', 119),
(22604005, 'Smith', 'Jane', 'D', 'BSCS', '3', 'jane.smith@example.com', 'janesmith', 'hashedpassword2', 'uploads/jane.jpg', 'student', 119),
(22604006, 'Brown', 'Charlie', 'A', 'BSIS', '1', 'charlie.brown@example.com', 'charliebrown', 'hashedpassword3', 'uploads/charlie.jpg', 'student', 119),
(22604007, 'White', 'Lucy', 'B', 'BSECE', '4', 'lucy.white@example.com', 'lucywhite', 'hashedpassword4', 'uploads/lucy.jpg', 'student', 119);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `FK_subject_id` (`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`idno`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `idno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22604017;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`idno`);

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
