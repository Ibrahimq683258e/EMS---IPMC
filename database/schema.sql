-- IPMC Employee Management System Schema
-- For IPMC Tamale Campus, Ghana
-- Highly professional and structured schema

CREATE DATABASE IF NOT EXISTS `ipmc_ems` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ipmc_ems`;

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Employees Table
CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(50) NOT NULL UNIQUE, -- Generated format: IPMC/TAM/2025/XXXX
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'HR', 'Employee') NOT NULL DEFAULT 'Employee',
    `staff_type` ENUM('Academic', 'Non-Academic') NOT NULL DEFAULT 'Non-Academic',
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `department_id` INT DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `joining_date` DATE NOT NULL,
    `photo` VARCHAR(255) DEFAULT NULL, -- Path to uploaded passport photo
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Leave Balances Table (Tracks remaining leave days per employee and leave type)
CREATE TABLE IF NOT EXISTS `leave_balances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `leave_type` ENUM('Annual', 'Sick', 'Casual', 'Maternity', 'Paternity', 'Study') NOT NULL,
    `allocated` INT NOT NULL DEFAULT 0,
    `used` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `emp_leave_type` (`employee_id`, `leave_type`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Leave Applications Table
CREATE TABLE IF NOT EXISTS `leaves` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `leave_type` ENUM('Annual', 'Sick', 'Casual', 'Maternity', 'Paternity', 'Study') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `days_requested` INT NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `action_by` INT DEFAULT NULL, -- Admin or HR user ID who made the decision
    `action_date` DATE DEFAULT NULL,
    `comments` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`action_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Attendance Table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `status` ENUM('Present', 'Absent', 'Late', 'Permission') NOT NULL DEFAULT 'Present',
    `time_in` TIME DEFAULT NULL,
    `time_out` TIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `emp_date` (`employee_id`, `date`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Performance Appraisals Table
CREATE TABLE IF NOT EXISTS `appraisals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `appraiser_id` INT NOT NULL, -- Admin/HR who appraised the employee
    `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comments` TEXT NOT NULL,
    `appraisal_period` VARCHAR(50) NOT NULL, -- e.g., "2025 - Q1", "Full Year 2025"
    `appraisal_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appraiser_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `created_by` INT NOT NULL, -- HR/Admin who posted the announcement
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Seed Sample Data
-- Insert Initial Departments
INSERT INTO `departments` (`name`, `code`, `description`) VALUES
('Academic Administration', 'ACAD-ADMIN', 'Covers curriculum design, academic standards, and program oversight.'),
('Computer Science & IT Faculty', 'CS-IT', 'Academic lecturers and lab instructors specializing in Information Technology.'),
('Human Resource & Finance', 'HR-FIN', 'Manages administration, staffing, accounting, and institutional operations.'),
('Student Affairs & Marketing', 'STUD-MKT', 'Handles admissions, public relations, academic counseling, and campus outreach.');

-- Insert Default Administrative & Staff Users (passwords are 'admin123', 'hr123', 'emp123' respectively)
INSERT INTO `employees` (`employee_id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `staff_type`, `gender`, `phone`, `department_id`, `designation`, `joining_date`, `status`) VALUES
('IPMC/TAM/2025/0001', 'Alhassan', 'Mubarak', 'admin@ipmc.edu.gh', '$2y$10$FT43MS9VE9LbSmrcnE2y.e9rdWYP9A1JvPm0k3PJc8K/AnqNG3lGu', 'Admin', 'Non-Academic', 'Male', '+233241234567', 1, 'Super Administrator / Campus Director', '2025-01-01', 'Active'),
('IPMC/TAM/2025/0002', 'Fuseini', 'Fatima', 'hr@ipmc.edu.gh', '$2y$10$Zg8Jiax4kl8b6IpQwPIXsurYUm9oqn/SFhdpOUtVxFvLinQYZLO2W', 'HR', 'Non-Academic', 'Female', '+233201234567', 3, 'Senior HR Officer', '2025-01-10', 'Active'),
('IPMC/TAM/2025/0003', 'Abubakari', 'Yakubu', 'yakubu@ipmc.edu.gh', '$2y$10$vte2odt4BtUAh.VOHEk6duuIRL4Zo4YdZxIf0Lyd61/lZHcHhJcku', 'Employee', 'Academic', 'Male', '+233261234567', 2, 'Senior IT Lecturer', '2025-02-01', 'Active'),
('IPMC/TAM/2025/0004', 'Aisha', 'Mohammed', 'aisha@ipmc.edu.gh', '$2y$10$vte2odt4BtUAh.VOHEk6duuIRL4Zo4YdZxIf0Lyd61/lZHcHhJcku', 'Employee', 'Non-Academic', 'Female', '+233271234567', 4, 'Admissions Counselor', '2025-02-15', 'Active');

-- Allocate Initial Leave Balances for the employees (Yakubu and Aisha)
-- Yakubu leave allocations
INSERT INTO `leave_balances` (`employee_id`, `leave_type`, `allocated`, `used`) VALUES
(3, 'Annual', 20, 0),
(3, 'Sick', 10, 0),
(3, 'Casual', 5, 0),
(4, 'Annual', 20, 0),
(4, 'Sick', 10, 0),
(4, 'Casual', 5, 0);

-- Insert Sample Leave Applications
INSERT INTO `leaves` (`employee_id`, `leave_type`, `start_date`, `end_date`, `days_requested`, `reason`, `status`, `action_by`, `action_date`, `comments`) VALUES
(3, 'Casual', '2025-03-01', '2025-03-03', 2, 'Attending family event in Kumasi.', 'Pending', NULL, NULL, NULL),
(4, 'Sick', '2025-02-20', '2025-02-22', 2, 'Medical checkup and dental treatment.', 'Approved', 2, '2025-02-19', 'Granted. Get well soon.');

-- Log Used Days in Leave Balances for approved leaves
UPDATE `leave_balances` SET `used` = 2 WHERE `employee_id` = 4 AND `leave_type` = 'Sick';

-- Insert Sample Attendance Records
INSERT INTO `attendance` (`employee_id`, `date`, `status`, `time_in`, `time_out`, `notes`) VALUES
(1, '2025-02-25', 'Present', '07:45:00', '17:00:00', 'Arrived early.'),
(2, '2025-02-25', 'Present', '07:55:00', '17:15:00', 'Regular attendance.'),
(3, '2025-02-25', 'Present', '08:15:00', '16:30:00', 'Lectures delivered on time.'),
(4, '2025-02-25', 'Late', '08:45:00', '17:05:00', 'Delayed due to traffic on Tamale-Bolgatanga highway.'),

(1, '2025-02-26', 'Present', '07:50:00', '17:00:00', NULL),
(2, '2025-02-26', 'Present', '08:00:00', '17:00:00', NULL),
(3, '2025-02-26', 'Permission', NULL, NULL, 'Attended university symposium.'),
(4, '2025-02-26', 'Present', '08:02:00', '17:00:00', NULL);

-- Insert Sample Appraisal
INSERT INTO `appraisals` (`employee_id`, `appraiser_id`, `rating`, `comments`, `appraisal_period`, `appraisal_date`) VALUES
(3, 2, 5, 'Yakubu demonstrates outstanding academic competence. He excels in mentoring IT students and organizing lab sessions.', '2025 - Q1', '2025-02-26');

-- Insert Sample Announcement
INSERT INTO `announcements` (`title`, `content`, `created_by`) VALUES
('Welcome to the New Academic Year!', 'Welcome back, academic and non-academic staff. Let us work together to make the 2025 academic year at IPMC Tamale Campus a stellar success! Please note that general staff meeting is on Friday.', 2),
('Submission of Course Outlines', 'All academic staff are requested to submit their course outlines for the first semester to the Academic Administration Department by the end of this week.', 1);
