-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 20, 2025 at 11:25 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cognitest`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `credits` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `semester`, `created_at`) VALUES
(2, 'CSE303', 'Machine Learning', 4, 5, '2025-02-25 05:07:17'),
(4, 'CSE305', 'Operating System', 4, 4, '2025-02-25 05:08:43'),
(5, 'CSE301', 'Software Engineering', 5, 5, '2025-02-25 05:09:31');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_courses`
--

CREATE TABLE `faculty_courses` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `papers`
--

CREATE TABLE `papers` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `total_marks` int(11) NOT NULL,
  `duration` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `exam_time` time DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `access_key` varchar(16) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `question_ids` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `papers`
--

INSERT INTO `papers` (`id`, `faculty_id`, `course_id`, `total_marks`, `duration`, `exam_date`, `exam_time`, `status`, `access_key`, `rejection_reason`, `question_ids`, `created_at`) VALUES
(8, 2, 4, 30, 60, '2025-03-24', '12:30:00', 'rejected', '36e79c34', 'There are no remembering level questions.', '6,10,14,15,17,21,22,25', '2025-03-19 12:27:44'),
(11, 2, 4, 30, 60, '2025-03-24', '12:30:00', 'approved', '28199a47', NULL, '21,22,26,9,7,13,21,22,10,18,26', '2025-03-19 13:35:16'),
(12, 2, 4, 15, 45, '2025-03-25', '09:15:00', 'approved', '5df330d3', NULL, '25,8,15,25', '2025-03-19 13:42:47');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `unit_number` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `marks` int(11) NOT NULL,
  `bloom_level` enum('remembering','understanding','analyzing','applying','evaluating','creating') DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `course_id`, `unit_number`, `question_text`, `marks`, `bloom_level`, `created_by`, `created_at`) VALUES
(2, 4, 1, 'What are the essential functions of an operating system?', 2, 'remembering', 2, '2025-02-27 05:09:51'),
(3, 4, 1, 'Explain Multiprogramming.', 2, 'remembering', 2, '2025-02-27 05:10:36'),
(4, 4, 1, 'What is Time-Sharing OS?', 2, 'remembering', 2, '2025-02-27 05:12:14'),
(6, 4, 1, 'Explain the batch processing system with an example.', 3, 'understanding', 2, '2025-02-27 05:16:40'),
(7, 4, 1, 'Compare Single-User OS and Multi-User OS.', 3, 'remembering', 2, '2025-02-27 05:21:43'),
(8, 4, 1, 'Explain the services provided by an OS.', 5, 'remembering', 2, '2025-02-27 05:22:32'),
(9, 4, 1, 'What is the role of the kernel in an OS?', 3, 'understanding', 2, '2025-02-27 05:24:07'),
(10, 4, 1, 'Explain the working of a Time-Sharing OS with a suitable example.', 5, 'understanding', 2, '2025-02-27 05:26:46'),
(11, 4, 2, 'Define a process and process state.', 2, 'applying', 2, '2025-02-27 05:27:24'),
(12, 4, 2, 'What is a Process Control Block (PCB)?', 2, 'understanding', 2, '2025-02-27 05:27:56'),
(13, 4, 2, 'Draw and explain the Process State Diagram.', 3, 'applying', 2, '2025-02-27 05:28:38'),
(14, 4, 2, 'Apply FCFS Scheduling Algorithm to a given process set and compute the average turn-around time.\nProcess     Arrival-time       Burst-time\nP1               0                       5\nP2               0                       3\nP3               0                       8\n', 3, 'applying', 2, '2025-02-27 05:32:41'),
(15, 4, 2, 'Compare the efficiency of Round Robin scheduling algorithm with that of priority based scheduling algorithm if the time quantum is 4ms with an example.', 5, 'evaluating', 2, '2025-02-27 05:34:56'),
(16, 4, 2, 'Compare Preemptive and Non-Preemptive Scheduling.', 2, 'analyzing', 2, '2025-02-27 05:36:03'),
(17, 4, 2, 'Analyze the advantages and disadvantages of Shortest Job Next (SJN) Scheduling.', 3, 'analyzing', 2, '2025-02-27 05:37:13'),
(18, 4, 2, 'Compare FCFS, SJN, Priority, and Round Robin Scheduling algorithms based on response time, throughput, and fairness.', 5, 'analyzing', 2, '2025-02-27 05:37:56'),
(19, 4, 3, 'What is Process Synchronization and why is it needed?', 2, 'evaluating', 2, '2025-02-27 10:13:15'),
(20, 4, 3, 'Define Race Condition.', 2, 'evaluating', 2, '2025-02-27 10:13:15'),
(21, 4, 3, 'Evaluate the effectiveness of Semaphores for process synchronization.', 3, 'evaluating', 2, '2025-02-27 10:13:15'),
(22, 4, 3, 'Assess the importance of Mutual Exclusion in process synchronization.', 3, 'evaluating', 2, '2025-02-27 10:13:15'),
(23, 4, 3, 'Justify the need for Peterson’s Solution and its correctness.', 5, 'evaluating', 2, '2025-02-27 10:13:15'),
(24, 4, 3, 'Evaluate the limitations of semaphores in preventing deadlock.', 5, 'evaluating', 2, '2025-02-27 10:13:15'),
(25, 4, 3, 'Design a solution for the Producer-Consumer Problem using semaphores.', 5, 'creating', 2, '2025-02-27 10:13:15'),
(26, 4, 3, 'Construct a real-world scenario where Deadlock can occur and propose a solution.', 5, 'creating', 2, '2025-02-27 10:13:15'),
(27, 4, 4, 'What are the necessary conditions for Deadlock?', 2, 'remembering', 2, '2025-02-27 10:13:15'),
(28, 4, 4, 'Explain Deadlock Prevention techniques.', 3, 'understanding', 2, '2025-02-27 10:13:15'),
(29, 4, 4, 'Solve a Banker’s Algorithm problem to check whether the system is in a safe state or not.', 5, 'applying', 2, '2025-02-27 10:13:15'),
(30, 4, 4, 'Compare Deadlock Prevention, Avoidance, and Detection methods.', 5, 'analyzing', 2, '2025-02-27 10:13:15'),
(31, 4, 5, 'Define Paging and Segmentation.', 2, 'remembering', 2, '2025-02-27 10:13:15'),
(32, 4, 5, 'What is Fragmentation?', 2, 'remembering', 2, '2025-02-27 10:13:15'),
(33, 4, 5, 'Explain Internal and External Fragmentation.', 3, 'understanding', 2, '2025-02-27 10:13:15'),
(34, 4, 5, 'Given a set of processes and memory blocks, apply the Best Fit and First Fit allocation strategies.', 5, 'applying', 2, '2025-02-27 10:13:15'),
(35, 4, 5, 'Assess the performance of Paging vs Segmentation.', 5, 'evaluating', 2, '2025-02-27 10:13:15'),
(36, 4, 6, 'What is Disk Scheduling?', 2, 'remembering', 2, '2025-02-27 10:13:15'),
(37, 4, 6, 'Explain the LOOK and C-LOOK scheduling algorithms.', 3, 'understanding', 2, '2025-02-27 10:13:15'),
(38, 4, 6, 'Apply the SCAN and C-SCAN Scheduling algorithms to a given disk request queue.', 5, 'applying', 2, '2025-02-27 10:13:15'),
(39, 4, 6, 'Compare FCFS, SSTF, SCAN, and C-SCAN Scheduling.', 5, 'analyzing', 2, '2025-02-27 10:13:15'),
(40, 4, 1, 'Explain Multithreading.', 2, 'remembering', 2, '2025-03-20 05:22:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('hod','faculty','coordinator') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `unique_course_code` (`code`);

--
-- Indexes for table `faculty_courses`
--
ALTER TABLE `faculty_courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `papers`
--
ALTER TABLE `papers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_key` (`access_key`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `faculty_courses`
--
ALTER TABLE `faculty_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `papers`
--
ALTER TABLE `papers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `faculty_courses`
--
ALTER TABLE `faculty_courses`
  ADD CONSTRAINT `faculty_courses_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `faculty_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `papers`
--
ALTER TABLE `papers`
  ADD CONSTRAINT `papers_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `papers_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
