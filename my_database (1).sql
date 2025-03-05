-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 26, 2025 at 01:37 AM
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
(1, 'SHEEESHSHH', 'SHEEEESH', '2025-02-25');

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_requests`
--

CREATE TABLE `sit_in_requests` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `course` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `hours` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sit_in_requests`
--

INSERT INTO `sit_in_requests` (`id`, `student_id`, `student_name`, `course`, `subject`, `date`, `hours`, `status`, `created_at`, `subject_id`) VALUES
(1, '0912', '', '', '', '0000-00-00', 0, 'approved', '2025-02-20 14:26:19', 2),
(2, '0912', '', '', '', '0000-00-00', 0, 'rejected', '2025-02-20 14:26:25', 2),
(3, '9090909090', '', '', '', '0000-00-00', 0, 'approved', '2025-02-20 16:12:57', 4),
(4, '9090909090', '', '', '', '0000-00-00', 0, 'approved', '2025-02-20 16:17:52', 5);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `sessions` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `date`, `start_time`, `end_time`, `sessions`) VALUES
(5, 'adas', '2025-02-15', '12:31:00', '12:31:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `idno` varchar(50) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `course` varchar(50) NOT NULL,
  `year` varchar(10) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `photo` varchar(255) NOT NULL DEFAULT 'default.png',
  `role` enum('admin','student') NOT NULL DEFAULT 'student',
  `remaining_hours` int(11) NOT NULL DEFAULT 120
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`idno`, `lastname`, `firstname`, `middlename`, `course`, `year`, `email`, `username`, `password_hash`, `photo`, `role`, `remaining_hours`) VALUES
('0912', 'Sheeeshs', 'SHOOOSh', 'SHAASHSHH', 'BSECE', '4', 'sheeeesh@gmail.com', 'mrpopoman12', '$2y$10$SIlY/Xv.1sV0EkMw84HTJOtfN8epf0WdA8L16q8/zf5Ee0xJVeHN6', 'uploads/1740059861_3.jpg', 'student', 120),
('9090909090', 'Alonso LABLAB', 'Zeke LABLAB', 'Debulosan', 'BSCS', '4', 'zekealonsoLABLAB@gmail.com', 'zeke', '$2y$10$w0d2vP5y.sVM0v3xgS5a7eztwWA/ys6x7B941r3r6Suli6V377IWW', 'uploads/1740067804_zeke.jpg', 'student', 120),
('9099090', 'Lazaga', 'Prince', 'sdfjhsjfh', 'BSIT', '4', 'lazagabayot@gmail.com', 'student1', '$2y$10$BImTZH9ud0W0Zin1Cs0Sce/V.ffzve4CizHTKpWSN4YmTKTWMYWJ2', 'uploads/1739462573_diwata.jpg', 'student', 120),
('admin_id', 'Admin', 'Admin', '', '', '', 'admin@example.com', 'admin', '$2y$10$lbf9rFCc2cXrWSyrxf1c.egIiuxLC80f.rzY1ymiDraabglVvR/yK', 'default.png', 'admin', 120);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sit_in_requests`
--
ALTER TABLE `sit_in_requests`
  ADD CONSTRAINT `sit_in_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`idno`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
