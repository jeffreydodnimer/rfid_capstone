-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 03, 2025 at 07:10 PM
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
-- Database: `rfid_capstone`
--

-- --------------------------------------------------------

--
-- Table structure for table `advisers`
--

CREATE TABLE `advisers` (
  `adviser_id` int(11) NOT NULL,
  `employee_id` int(20) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `pass` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `advisers`
--

INSERT INTO `advisers` (`adviser_id`, `employee_id`, `lastname`, `firstname`, `middlename`, `suffix`, `gender`, `username`, `pass`) VALUES
(1, 12345, 'Rowan', 'Jenny', 'Amandi', '', 'female', 'jennyrowan', 'jenny123'),
(2, 12344, 'Perez', 'Louver', 'Delaney', '', 'male', 'louverperez', 'louver122'),
(3, 12343, 'Dela Rosa', 'Lovely', 'Kova', '', 'male', 'lovelydela rosa', 'lovely121'),
(4, 12342, 'Buag', 'Irene', 'Sterling', '', 'female', 'irenebuag', 'irene120'),
(5, 12341, 'Aranilla', 'Pricess', 'Nguyen', '', 'female', 'pricessaranilla', 'princess119'),
(6, 12346, 'Buendia', 'Jenifer', 'Callaghan', '', 'female', 'jeniferbuendia', 'jenifer118'),
(7, 12347, 'Ayapana', 'Jane', 'Petrov', '', 'female', 'janeayapana', 'jane117'),
(8, 12348, 'De Guzman', 'Peter', 'Mansour', '', 'female', 'peterde guzman', 'peter116'),
(9, 12349, 'Parker', 'Juan', 'Vasquez', '', 'male', 'juanparker', 'juan115'),
(10, 12310, 'Dela Cruz', 'John', 'Hendrix', '', 'male', 'johndela cruz', 'john114'),
(11, 12311, 'Wreck', 'Carl', 'Tanaka', '', 'male', 'carlwreck', 'carl113'),
(12, 12312, 'Agustin', 'Piolo', 'Morales', '', 'male', 'pioloagustin', 'piolo112');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `lrn` bigint(50) DEFAULT NULL,
  `enrollment_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `lrn`, `enrollment_id`, `date`, `time_in`, `time_out`, `status`) VALUES
(1, 108959090007, 6, '2025-12-04', '2025-12-04 01:28:02', '2025-12-04 01:40:49', 'present'),
(2, 108959090006, 5, '2025-12-04', '2025-12-04 01:28:08', '2025-12-04 01:40:46', 'present'),
(3, 108959090005, 4, '2025-12-04', '2025-12-04 01:28:15', '2025-12-04 01:40:42', 'present'),
(4, 108959090001, 1, '2025-12-04', '2025-12-04 01:28:22', '2025-12-04 01:40:38', 'present'),
(5, 108959090008, 7, '2025-12-04', '2025-12-04 01:28:28', '2025-12-04 01:40:34', 'present'),
(6, 108959090010, 9, '2025-12-04', '2025-12-04 01:28:34', '2025-12-04 01:40:30', 'present'),
(7, 108959090011, 10, '2025-12-04', '2025-12-04 01:28:46', '2025-12-04 01:40:27', 'present'),
(8, 108959090004, 3, '2025-12-04', '2025-12-04 01:28:55', '2025-12-04 01:40:23', 'present'),
(9, 108959090012, 11, '2025-12-04', '2025-12-04 01:29:05', '2025-12-04 01:40:05', 'present'),
(10, 108959090009, 8, '2025-12-04', '2025-12-04 01:29:07', '2025-12-04 01:40:20', 'present');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `lrn` bigint(50) DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `school_year` varchar(9) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `lrn`, `grade_level`, `section_id`, `school_year`) VALUES
(1, 108959090001, '7', 1, '2025-2026'),
(2, 108959090002, '7', 2, '2025-2026'),
(3, 108959090004, '8', 4, '2025-2026'),
(4, 108959090005, '12-TVL', 5, '2025-2026'),
(5, 108959090006, '12-GAS', 6, '2025-2026'),
(6, 108959090007, '11-GAS', 7, '2025-2026'),
(7, 108959090008, '11-TVL', 8, '2025-2026'),
(8, 108959090009, '10', 9, '2025-2026'),
(9, 108959090010, '10', 10, '2025-2026'),
(10, 108959090011, '9', 11, '2025-2026'),
(11, 108959090012, '9', 12, '2025-2026');

-- --------------------------------------------------------

--
-- Table structure for table `guardians`
--

CREATE TABLE `guardians` (
  `guardian_id` int(11) NOT NULL,
  `lrn` bigint(50) NOT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `contact_number` bigint(50) DEFAULT NULL,
  `relationship_to_student` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guardians`
--

INSERT INTO `guardians` (`guardian_id`, `lrn`, `lastname`, `firstname`, `middlename`, `suffix`, `contact_number`, `relationship_to_student`) VALUES
(57, 108959090001, 'Odnimer', 'Merlin', 'Delin', '', 9123456789, 'Mother'),
(59, 108959090012, 'Noscal', 'cholo', 'Marquez', '', 9123456787, 'Bother'),
(60, 108959090004, 'De Leon', 'kenneth', 'Eroles', '', 9123456785, 'Brother'),
(61, 108959090005, 'Dichosa', 'Venus', 'Eroles', '', 9123456784, 'Mother'),
(62, 108959090006, 'Glorioso', 'Myra', 'Delin', '', 9123456783, 'Mother'),
(63, 108959090007, 'Medina', 'Papsi', 'Cambarihan', '', 9123456782, 'Brother'),
(64, 108959090008, 'Llanes', 'Bea', 'Veran', '', 9123456781, 'Sister'),
(65, 108959090009, 'Ocan', 'Renoel', 'De Leon', '', 9123456779, 'Brother'),
(66, 108959090010, 'Altez', 'Alyssa', 'Mapaye', '', 9123456778, 'Sister'),
(67, 108959090011, 'Fabriquel', 'Deo', 'Enad', '', 9123456777, 'Brother'),
(68, 108959090002, 'Agang', 'Haily', 'Delos', '', 9123456788, 'Sister');

-- --------------------------------------------------------

--
-- Table structure for table `rfid`
--

CREATE TABLE `rfid` (
  `rfid_id` int(11) NOT NULL,
  `rfid_number` int(50) DEFAULT NULL,
  `lrn` bigint(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid`
--

INSERT INTO `rfid` (`rfid_id`, `rfid_number`, `lrn`) VALUES
(1, 1189966981, 108959090001),
(2, 2147483647, 108959090002),
(3, 1225942085, 108959090004),
(4, 1189257061, 108959090005),
(5, 1226471845, 108959090006),
(6, 1224983381, 108959090007),
(7, 1226163477, 108959090008),
(8, 1225351493, 108959090009),
(9, 1225902677, 108959090010),
(10, 1450823369, 108959090011),
(11, 1225600245, 108959090012);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `adviser_id` int(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `section_name`, `grade_level`, `adviser_id`) VALUES
(1, 'Emerald', '7', 1),
(2, 'Courage', '7', 2),
(3, 'Integrity', '8', 3),
(4, 'Aquamarine', '8', 4),
(5, 'Excellence', '12-TVL', 5),
(6, 'Diamond', '12-GAS', 6),
(7, 'Harmony', '11-GAS', 7),
(8, 'Ruby', '11-TVL', 8),
(9, 'Perseverance', '10', 9),
(10, 'Wisdom', '10', 10),
(11, 'Charity', '9', 11),
(12, 'Amethyst', '9', 12);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `lrn` bigint(50) NOT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `age` int(50) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`lrn`, `lastname`, `firstname`, `middlename`, `suffix`, `age`, `birthdate`, `profile_image`) VALUES
(108959090001, 'Odnimer', 'Jeffrey', 'Delin', '0', 22, '2003-05-31', ''),
(108959090002, 'Agang', 'Hail', 'Delos', '0', 20, '2005-06-25', 'student_692b417c1154a3.70638029.jpg'),
(108959090004, 'De Leon', 'Lawrence', 'Eroles', '0', 22, '2003-09-10', NULL),
(108959090005, 'Dichosa', 'Alexis', 'Eroles', '0', 22, '2003-10-28', NULL),
(108959090006, 'Glorioso', 'Andrei', 'Delin', '0', 23, '2002-11-22', NULL),
(108959090007, 'Medina', 'Jessie', 'Cambarihan', '0', 21, '2004-12-19', 'student_692b4687d2f6f9.80623940.png'),
(108959090008, 'Llanes', 'Beatrice', 'Veran', '0', 19, '2006-03-15', NULL),
(108959090009, 'Ocan', 'Isabela', 'De Leon', '0', 21, '2004-04-21', NULL),
(108959090010, 'Altez', 'Cyrell', 'Mapaye', '0', 20, '2005-02-11', 'student_692c86b8596413.49791755.jpg'),
(108959090011, 'Fabriquel', 'Jnrix', 'Enad', '0', 23, '2002-01-01', NULL),
(108959090012, 'Noscal', 'Jolo', 'Marquez', '0', 20, '2005-07-04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `time_settings`
--

CREATE TABLE `time_settings` (
  `id` int(11) NOT NULL,
  `morning_start` time NOT NULL,
  `morning_end` time NOT NULL,
  `morning_late_threshold` time NOT NULL,
  `afternoon_start` time NOT NULL,
  `afternoon_end` time NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `allow_mon` tinyint(1) NOT NULL DEFAULT 1,
  `allow_tue` tinyint(1) NOT NULL DEFAULT 1,
  `allow_wed` tinyint(1) NOT NULL DEFAULT 1,
  `allow_thu` tinyint(1) NOT NULL DEFAULT 1,
  `allow_fri` tinyint(1) NOT NULL DEFAULT 1,
  `allow_sat` tinyint(1) NOT NULL DEFAULT 0,
  `allow_sun` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_settings`
--

INSERT INTO `time_settings` (`id`, `morning_start`, `morning_end`, `morning_late_threshold`, `afternoon_start`, `afternoon_end`, `updated_at`, `allow_mon`, `allow_tue`, `allow_wed`, `allow_thu`, `allow_fri`, `allow_sat`, `allow_sun`) VALUES
(1, '01:28:00', '01:35:00', '01:30:00', '01:40:00', '01:45:00', '2025-12-03 17:27:06', 1, 1, 1, 1, 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','faculty') NOT NULL,
  `status` varchar(20) DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `status`) VALUES
(1, 'odnimerjeffreyd@gmail.com', '123', 'admin', 'active'),
(2, 'ronroncapulong@gmail.com', 'pass', 'admin', 'inactive');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `advisers`
--
ALTER TABLE `advisers`
  ADD PRIMARY KEY (`adviser_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `lrn` (`lrn`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `lrn` (`lrn`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `guardians`
--
ALTER TABLE `guardians`
  ADD PRIMARY KEY (`guardian_id`),
  ADD KEY `lrn` (`lrn`);

--
-- Indexes for table `rfid`
--
ALTER TABLE `rfid`
  ADD PRIMARY KEY (`rfid_id`),
  ADD UNIQUE KEY `lrn` (`lrn`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `unique_adviser_per_grade` (`adviser_id`,`grade_level`),
  ADD UNIQUE KEY `adviser_grade_unique` (`adviser_id`,`grade_level`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`lrn`);

--
-- Indexes for table `time_settings`
--
ALTER TABLE `time_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `advisers`
--
ALTER TABLE `advisers`
  MODIFY `adviser_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `guardians`
--
ALTER TABLE `guardians`
  MODIFY `guardian_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `rfid`
--
ALTER TABLE `rfid`
  MODIFY `rfid_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `time_settings`
--
ALTER TABLE `time_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`);

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`);

--
-- Constraints for table `guardians`
--
ALTER TABLE `guardians`
  ADD CONSTRAINT `guardians_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`);

--
-- Constraints for table `rfid`
--
ALTER TABLE `rfid`
  ADD CONSTRAINT `rfid_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`);

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`adviser_id`) REFERENCES `advisers` (`adviser_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
