-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 19, 2025 at 08:33 AM
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
  `employee_id` int(20) NOT NULL,
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

INSERT INTO `advisers` (`employee_id`, `lastname`, `firstname`, `middlename`, `suffix`, `gender`, `username`, `pass`) VALUES
(12310, 'Dela Cruz', 'John', 'Hendrix', '', 'male', 'johndela cruz', 'john114'),
(12311, 'Wreck', 'Carl', 'Tanaka', '', 'male', 'carlwreck', 'carl113'),
(12312, 'Agustin', 'Piolo', 'Morales', '', 'male', 'pioloagustin', 'piolo112'),
(12341, 'Aranilla', 'Pricess', 'Nguyen', '', 'female', 'pricessaranilla', 'princess119'),
(12342, 'Buag', 'Irene', 'Sterling', '', 'female', 'irenebuag', 'irene120'),
(12343, 'Dela Rosa', 'Lovely', 'Kova', '', 'male', 'lovelydela rosa', 'lovely121'),
(12344, 'Perez', 'Louver', 'Delaney', '', 'male', 'louverperez', 'louver122'),
(12345, 'Rowan', 'Jenny', 'Amandi', '', 'female', 'jennyrowan', 'jenny123'),
(12346, 'Buendia', 'Jenifer', 'Callaghan', '', 'female', 'jeniferbuendia', 'jenifer118'),
(12347, 'Ayapana', 'Jane', 'Petrov', '', 'female', 'janeayapana', 'jane117'),
(12348, 'De Guzman', 'Peter', 'Mansour', '', 'female', 'peterde guzman', 'peter116'),
(12349, 'Parker', 'Juan', 'Vasquez', '', 'male', 'juanparker', 'juan115');

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

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `lrn` bigint(50) DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `school_year` varchar(9) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `lrn`, `grade_level`, `section_name`, `school_year`) VALUES
(1, 108959090001, '7', 'Emerald', '2025-2026'),
(2, 108959090002, '11-TVL', 'Ruby', '2025-2026'),
(3, 108959090003, '7', 'Emerald', '2025-2026'),
(4, 108959090004, '7', 'Emerald', '2025-2026'),
(5, 108959090005, '7', 'Emerald', '2025-2026'),
(6, 108959090006, '7', 'Emerald', '2025-2026'),
(7, 108959090007, '7', 'Emerald', '2025-2026'),
(8, 108959090008, '7', 'Emerald', '2025-2026'),
(9, 108959090009, '7', 'Emerald', '2025-2026'),
(10, 108959090010, '7', 'Emerald', '2025-2026'),
(11, 108959090011, '7', 'Emerald', '2025-2026'),
(12, 108959090012, '7', 'Emerald', '2025-2026'),
(13, 108959090013, '7', 'Emerald', '2025-2026');

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
(1, 108959090001, 'Odnimer', 'Merlin', 'Delin', '', 9123456789, 'Mother'),
(2, 108959090002, 'Agang', 'Haily', 'Delos', '', 9123456788, 'Sister'),
(3, 108959090003, 'Noscal', 'cholo', 'Marquez', '', 9123456787, 'Bother'),
(4, 108959090004, 'Binabay', 'Mark', 'Marasigan', '', 9123456786, 'Brother'),
(5, 108959090005, 'De Leon', 'kenneth', 'Eroles', '', 9123456785, 'Brother'),
(6, 108959090006, 'Dichosa', 'Venus', 'Eroles', '', 9123456784, 'Mother'),
(7, 108959090007, 'Glorioso', 'Myra', 'Delin', '', 9123456783, 'Mother'),
(8, 108959090008, 'Medina', 'Papsi', 'Cambarihan', '', 9123456782, 'Brother'),
(9, 108959090009, 'Llanes', 'Bea', 'Veran', '', 9123456781, 'Sister'),
(10, 108959090010, 'Ocan', 'Renoel', 'De Leon', '', 9123456779, 'Brother'),
(11, 108959090011, 'Altez', 'Alyssa', 'Mapaye', '', 9123456778, 'Sister'),
(12, 108959090012, 'Fabriquel', 'Deo', 'Enad', '', 9123456777, 'Brother'),
(13, 108959090013, 'Eroles', 'Ding DongRivera', '', '', 9811670811, 'Father');

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
(1, 1190858501, 108959090001),
(2, 2147483647, 108959090002),
(3, 1226471845, 108959090003),
(4, 1189257061, 108959090004),
(5, 1225942085, 108959090005),
(6, 1450823369, 108959090006),
(7, 1224983381, 108959090007),
(8, 1189966981, 108959090008),
(9, 1225600245, 108959090009),
(10, 1225902677, 108959090010),
(11, 1226163477, 108959090011),
(12, 1225351493, 108959090012),
(13, 2147483647, 108959090013);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `employee_id` int(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `section_name`, `grade_level`, `employee_id`) VALUES
(1, 'Emerald', '7', 12345),
(2, 'Courage', '7', 12344),
(3, 'Integrity', '8', 12343),
(4, 'Aquamarine', '8', 12342),
(5, 'Excellence', '12-TVL', 12341),
(6, 'Diamond', '12-GAS', 12346),
(7, 'Harmony', '11-GAS', 12347),
(8, 'Ruby', '11-TVL', 12348),
(9, 'Perseverance', '10', 12349),
(10, 'Wisdom', '10', 12310),
(11, 'Charity', '9', 12311),
(12, 'Amethyst', '9', 12312);

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
(108959090001, 'Odnimer', 'Jeffrey', 'Delin', '0', 22, '2003-05-31', NULL),
(108959090002, 'Agang', 'Hail', 'Delos', '0', 20, '2005-06-25', 'student_6944ff322d4a40.54263198.png'),
(108959090003, 'Noscal', 'Jolo', 'Marquez', '0', 20, '2005-07-04', NULL),
(108959090004, 'Binabay', 'Mark Ian', 'Marasigan', '0', 21, '2004-08-09', NULL),
(108959090005, 'De Leon', 'Lawrence', 'Eroles', '0', 22, '2003-09-10', NULL),
(108959090006, 'Dichosa', 'Alexis', 'Eroles', '0', 22, '2003-10-28', NULL),
(108959090007, 'Glorioso', 'Andrei', 'Delin', '0', 23, '2002-11-22', NULL),
(108959090008, 'Medina', 'Jessie', 'Cambarihan', '0', 21, '2004-12-19', NULL),
(108959090009, 'Llanes', 'Beatrice', 'Veran', '0', 19, '2006-03-15', NULL),
(108959090010, 'Ocan', 'Isabela', 'De Leon', '0', 21, '2004-04-21', NULL),
(108959090011, 'Altez', 'Cyrell', 'Mapaye', '0', 20, '2005-02-11', NULL),
(108959090012, 'Fabriquel', 'Jnrix', 'Enad', '0', 23, '2002-01-01', NULL),
(108959090013, 'Eroles', 'Jhon Carlo', 'Rivera', '0', 23, '2002-12-16', NULL);

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
  ADD PRIMARY KEY (`employee_id`),
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
  ADD KEY `lrn` (`lrn`);

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
  ADD UNIQUE KEY `unique_employee_per_grade` (`employee_id`,`grade_level`);

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
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `guardians`
--
ALTER TABLE `guardians`
  MODIFY `guardian_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `rfid`
--
ALTER TABLE `rfid`
  MODIFY `rfid_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`);

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
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `advisers` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
