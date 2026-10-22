-- ISLAMIYYA SCHOOL MANAGEMENT SYSTEM (ISMS)
-- Complete Database Schema & Seed Data

CREATE DATABASE IF NOT EXISTS `islamiyya_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `islamiyya_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables to allow fresh setup if re-run
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notification_recipients`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `receipts`;
DROP TABLE IF EXISTS `payment_allocations`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `student_invoice_items`;
DROP TABLE IF EXISTS `student_invoices`;
DROP TABLE IF EXISTS `fee_structures`;
DROP TABLE IF EXISTS `fee_categories`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `parents`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `academic_terms`;
DROP TABLE IF EXISTS `academic_sessions`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `school_settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. SCHOOL SETTINGS
CREATE TABLE `school_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS (Admins & Parents)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `email` VARCHAR(120) NULL UNIQUE,
  `phone` VARCHAR(25) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'parent') NOT NULL DEFAULT 'parent',
  `status` ENUM('active', 'suspended', 'deactivated') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PARENTS PROFILE
CREATE TABLE `parents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `residential_address` TEXT NOT NULL,
  `emergency_contact_name` VARCHAR(150) NULL,
  `emergency_contact_phone` VARCHAR(25) NULL,
  `occupation` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_parents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CLASSES (Islamiyya Levels)
CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(100) NOT NULL,
  `level_category` VARCHAR(50) NOT NULL, -- e.g., 'Rawdah', 'Ibtida\'iyyah', 'Mutawassitah', 'Thanawiyyah', 'Tahfeez'
  `description` VARCHAR(255) NULL,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ACADEMIC SESSIONS
CREATE TABLE `academic_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_name` VARCHAR(50) NOT NULL UNIQUE, -- e.g., '2025/2026'
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ACADEMIC TERMS
CREATE TABLE `academic_terms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `term_name` VARCHAR(50) NOT NULL, -- 'First Term', 'Second Term', 'Third Term'
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_term_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. STUDENTS
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id_number` VARCHAR(50) NULL UNIQUE, -- Assigned upon admin approval e.g., ISMS/2026/001
  `parent_id` INT NOT NULL,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `other_name` VARCHAR(60) NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` ENUM('Male', 'Female') NOT NULL,
  `requested_class_id` INT NULL,
  `current_class_id` INT NULL,
  `previous_islamiyya_education` TEXT NULL,
  `registration_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` TEXT NULL,
  `enrolment_status` ENUM('active', 'graduated', 'withdrawn', 'inactive') NOT NULL DEFAULT 'active',
  `admission_date` DATE NULL,
  `medical_info` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_req_class` FOREIGN KEY (`requested_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_students_cur_class` FOREIGN KEY (`current_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. FEE CATEGORIES
CREATE TABLE `fee_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE, -- e.g., 'Tuition Fee', 'Registration Fee', 'Exam Fee', 'Books & Materials', 'Uniform'
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. FEE STRUCTURES (Fees set for classes per session/term)
CREATE TABLE `fee_structures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `term_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `fee_category_id` INT NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `due_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_fee_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fee_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fee_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fee_category` FOREIGN KEY (`fee_category_id`) REFERENCES `fee_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. STUDENT INVOICES
CREATE TABLE `student_invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `student_id` INT NOT NULL,
  `session_id` INT NOT NULL,
  `term_id` INT NOT NULL,
  `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('unpaid', 'partially_paid', 'paid', 'overdue') NOT NULL DEFAULT 'unpaid',
  `due_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_inv_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. STUDENT INVOICE ITEMS
CREATE TABLE `student_invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `fee_category_id` INT NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `student_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_fee_cat` FOREIGN KEY (`fee_category_id`) REFERENCES `fee_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. PAYMENTS
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `parent_id` INT NOT NULL,
  `payment_reference` VARCHAR(100) NOT NULL UNIQUE, -- Internal unique ref e.g. PAY-20260418-XXXX
  `transaction_reference` VARCHAR(150) NULL,      -- Gateway ref or Bank teller/transfer ref
  `amount` DECIMAL(12, 2) NOT NULL,
  `payment_method` ENUM('online_paystack', 'online_flutterwave', 'bank_transfer', 'cash', 'pos') NOT NULL DEFAULT 'bank_transfer',
  `payment_channel` VARCHAR(50) NULL,
  `proof_file` VARCHAR(255) NULL,                 -- File path for bank transfer screenshot/teller
  `payment_status` ENUM('pending', 'successful', 'failed', 'rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` TEXT NULL,
  `verified_by` INT NULL,                         -- Admin user ID who verified
  `verified_at` DATETIME NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `student_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_admin` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. PAYMENT ALLOCATIONS
CREATE TABLE `payment_allocations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT NOT NULL,
  `invoice_id` INT NOT NULL,
  `amount_allocated` DECIMAL(12, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_alloc_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `student_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. RECEIPTS
CREATE TABLE `receipts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_number` VARCHAR(50) NOT NULL UNIQUE,  -- E.g. REC-2026-0001
  `payment_id` INT NOT NULL UNIQUE,
  `invoice_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `parent_id` INT NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `issued_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rec_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `student_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. NOTIFICATIONS
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `target_type` ENUM('all', 'selected_parent', 'class') NOT NULL DEFAULT 'all',
  `target_parent_id` INT NULL,
  `target_class_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_target_parent` FOREIGN KEY (`target_parent_id`) REFERENCES `parents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_target_class` FOREIGN KEY (`target_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. NOTIFICATION RECIPIENTS
CREATE TABLE `notification_recipients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `notification_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL,
  CONSTRAINT `fk_recip_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recip_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. AUDIT LOGS
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- SEED INITIAL DATA
-- =========================================================================

-- School Settings
INSERT INTO `school_settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'Darul Huda Islamic Academy'),
('school_motto', 'Excellence in Quranic and Arabic Education'),
('school_address', 'Plot 14, Al-Hikmah Way, Off Ahmadu Bello Way, Abuja, Nigeria'),
('school_phone', '+234 803 123 4567, +234 812 987 6543'),
('school_email', 'info@darulhuda.edu.ng'),
('currency_symbol', '₦'),
('currency_code', 'NGN'),
('bank_name', 'Jaiz Bank PLC'),
('bank_account_name', 'Darul Huda Islamic Academy'),
('bank_account_number', '0012345678'),
('paystack_public_key', 'pk_test_sample_key_123456789'),
('paystack_secret_key', 'sk_test_sample_secret_987654321'),
('paystack_enabled', '1'),
('flutterwave_public_key', 'FLWPUBK_TEST-sample-key-123'),
('flutterwave_secret_key', 'FLWSECK_TEST-sample-secret-456'),
('flutterwave_enabled', '0'),
('receipt_footer_note', 'All fees paid are non-refundable. For fee inquiries, please contact the school bursary.');

-- Academic Sessions
INSERT INTO `academic_sessions` (`id`, `session_name`, `is_current`) VALUES
(1, '2025/2026', 1),
(2, '2026/2027', 0);

-- Academic Terms
INSERT INTO `academic_terms` (`id`, `session_id`, `term_name`, `start_date`, `end_date`, `is_current`) VALUES
(1, 1, 'First Term', '2025-09-15', '2025-12-19', 0),
(2, 1, 'Second Term', '2026-01-12', '2026-04-10', 0),
(3, 1, 'Third Term', '2026-04-27', '2026-07-24', 1);

-- Islamiyya Classes / Levels
INSERT INTO `classes` (`id`, `class_name`, `level_category`, `description`, `display_order`, `status`) VALUES
(1, 'Rawdah (Early Years Islamic Nursery)', 'Rawdah', 'Foundational Arabic letters, basic Adhkaar, and Surah Al-Fatihah to An-Nas', 1, 'active'),
(2, 'Tahfeez Al-Quran Level 1', 'Tahfeez', 'Intensive Quranic memorization (Juz Amma & Tabarak)', 2, 'active'),
(3, 'Tahfeez Al-Quran Level 2', 'Tahfeez', 'Quranic memorization with Tajweed and Tuhfatul Atfal rules', 3, 'active'),
(4, 'Ibtida\'iyyah 1 (Primary 1)', 'Ibtida\'iyyah', 'Quran reading, Fiqh of Taharah & Salah, Tawheed fundamentals', 4, 'active'),
(5, 'Ibtida\'iyyah 2 (Primary 2)', 'Ibtida\'iyyah', 'Quran recitation, Seerah of the Prophet (SAW), Hadith Al-Arba\'in basics', 5, 'active'),
(6, 'Ibtida\'iyyah 3 (Primary 3)', 'Ibtida\'iyyah', 'Arabic Language (Duroosul Lughah), Fiqh, Quran memorization', 6, 'active'),
(7, 'Mutawassitah 1 (Junior 1)', 'Mutawassitah', 'Intermediate Arabic grammar (Nahw & Sarf), Fiqh Al-Muyassar, Quran', 7, 'active'),
(8, 'Mutawassitah 2 (Junior 2)', 'Mutawassitah', 'Arabic literature, Hadith studies, Islamic Ethics (Adaab)', 8, 'active'),
(9, 'Thanawiyyah 1 (Senior 1)', 'Thanawiyyah', 'Advanced Islamic jurisprudence, Tafseer, Arabic rhetoric (Balaghah)', 9, 'active');

-- Fee Categories
INSERT INTO `fee_categories` (`id`, `name`, `description`) VALUES
(1, 'Tuition Fee', 'Main instructional and educational fee for the term'),
(2, 'Registration Fee', 'One-time admission and registration charge for new students'),
(3, 'Examination & Assessment Fee', 'Termly examinations and report booklet fee'),
(4, 'Books & Learning Materials', 'Quran, Qa\'idah Nooraniyyah, Arabic textbooks and workbooks'),
(5, 'Islamiyya Uniform & Cap/Hijab', 'Official school uniform and head covering');

-- Fee Structures (Current Session 1, Term 3)
INSERT INTO `fee_structures` (`session_id`, `term_id`, `class_id`, `fee_category_id`, `amount`, `due_date`) VALUES
-- Rawdah
(1, 3, 1, 1, 25000.00, '2026-05-15'),
(1, 3, 1, 3, 3000.00, '2026-05-15'),
(1, 3, 1, 4, 7000.00, '2026-05-15'),
-- Tahfeez 1
(1, 3, 2, 1, 30000.00, '2026-05-15'),
(1, 3, 2, 3, 3000.00, '2026-05-15'),
(1, 3, 2, 4, 8000.00, '2026-05-15'),
-- Ibtida'iyyah 1
(1, 3, 4, 1, 28000.00, '2026-05-15'),
(1, 3, 4, 3, 3500.00, '2026-05-15'),
(1, 3, 4, 4, 7500.00, '2026-05-15'),
-- Mutawassitah 1
(1, 3, 7, 1, 35000.00, '2026-05-15'),
(1, 3, 7, 3, 4000.00, '2026-05-15'),
(1, 3, 7, 4, 9000.00, '2026-05-15');

-- DEFAULT ADMIN USER (Password: Admin@123)
-- Hash generated via password_hash('Admin@123', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password_hash`, `role`, `status`) VALUES
(1, 'admin', 'admin@darulhuda.edu.ng', '08031234567', '$2y$10$wTf7xK.82n58rS8jA3e1A.uD2Fp2xW88n7aL3l9jM6hR7G8.P2a5S', 'admin', 'active');

-- SAMPLE PARENT USER (Password: Parent@123)
-- Hash generated via password_hash('Parent@123', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password_hash`, `role`, `status`) VALUES
(2, 'parent_ibrahim', 'ibrahim.danladi@example.com', '08023456789', '$2y$10$zS3cOqCgVzX.pI6D58G2UeC4fE2N0Fz2.H1o4k8v6rE9w7y0M5V6G', 'parent', 'active'),
(3, 'parent_fatima', 'fatima.bello@example.com', '08123456780', '$2y$10$zS3cOqCgVzX.pI6D58G2UeC4fE2N0Fz2.H1o4k8v6rE9w7y0M5V6G', 'parent', 'active');

-- PARENT PROFILES
INSERT INTO `parents` (`id`, `user_id`, `full_name`, `residential_address`, `emergency_contact_name`, `emergency_contact_phone`, `occupation`) VALUES
(1, 2, 'Alhaji Ibrahim Danladi', 'House 22, Gwarinpa Estate, Abuja', 'Hajiya Zainab Danladi', '08098765432', 'Civil Servant'),
(2, 3, 'Dr. Fatima Bello', 'No. 8 Crescent, Wuse 2, Abuja', 'Mal. Bello Abubakar', '08033344455', 'Lecturer');

-- SAMPLE APPROVED STUDENTS FOR PARENT 1
INSERT INTO `students` (`id`, `student_id_number`, `parent_id`, `first_name`, `last_name`, `other_name`, `date_of_birth`, `gender`, `requested_class_id`, `current_class_id`, `previous_islamiyya_education`, `registration_status`, `enrolment_status`, `admission_date`) VALUES
(1, 'ISMS/2026/001', 1, 'Muhammad', 'Danladi', 'Al-Ameen', '2017-04-12', 'Male', 4, 4, 'Completed Nursery Arabic Qa\'idah at previous school', 'approved', 'active', '2025-09-15'),
(2, 'ISMS/2026/002', 1, 'Maryam', 'Danladi', 'Khadijah', '2019-08-20', 'Female', 1, 1, 'None (First time enrolment)', 'approved', 'active', '2025-09-15');

-- SAMPLE PENDING REGISTRATION FOR PARENT 2 (Ready for admin approval demonstration)
INSERT INTO `students` (`id`, `student_id_number`, `parent_id`, `first_name`, `last_name`, `other_name`, `date_of_birth`, `gender`, `requested_class_id`, `current_class_id`, `previous_islamiyya_education`, `registration_status`, `enrolment_status`, `admission_date`) VALUES
(3, NULL, 2, 'Abdullah', 'Bello', 'Zayd', '2016-11-05', 'Male', 2, NULL, 'Memorized Juz Amma at home madrasah', 'pending', 'active', NULL);

-- SAMPLE INVOICES
-- Muhammad Danladi (Ibtida'iyyah 1): Tuition (28000) + Exam (3500) + Books (7500) = 39,000
INSERT INTO `student_invoices` (`id`, `invoice_number`, `student_id`, `session_id`, `term_id`, `total_amount`, `amount_paid`, `balance`, `status`, `due_date`) VALUES
(1, 'INV-2026-0001', 1, 1, 3, 39000.00, 39000.00, 0.00, 'paid', '2026-05-15'),
-- Maryam Danladi (Rawdah): Tuition (25000) + Exam (3000) + Books (7000) = 35,000. Paid 20,000, balance 15,000
(2, 'INV-2026-0002', 2, 1, 3, 35000.00, 20000.00, 15000.00, 'partially_paid', '2026-05-15');

-- INVOICE ITEMS
INSERT INTO `student_invoice_items` (`invoice_id`, `fee_category_id`, `amount`) VALUES
(1, 1, 28000.00),
(1, 3, 3500.00),
(1, 4, 7500.00),
(2, 1, 25000.00),
(2, 3, 3000.00),
(2, 4, 7000.00);

-- SAMPLE PAYMENTS
-- Payment 1: Full payment for Muhammad Danladi (Paystack Online)
INSERT INTO `payments` (`id`, `invoice_id`, `student_id`, `parent_id`, `payment_reference`, `transaction_reference`, `amount`, `payment_method`, `payment_channel`, `payment_status`, `verified_by`, `verified_at`, `notes`) VALUES
(1, 1, 1, 1, 'PAY-20260401-0001', 'PSTK_9876543210', 39000.00, 'online_paystack', 'card', 'successful', 1, '2026-04-01 10:15:00', 'Online payment verified via Paystack webhook');

-- Payment 2: Partial payment for Maryam Danladi (Bank Transfer - Approved by Admin)
INSERT INTO `payments` (`id`, `invoice_id`, `student_id`, `parent_id`, `payment_reference`, `transaction_reference`, `amount`, `payment_method`, `payment_channel`, `proof_file`, `payment_status`, `verified_by`, `verified_at`, `notes`) VALUES
(2, 2, 2, 1, 'PAY-20260405-0002', 'JAIZ_TRF_4432190', 20000.00, 'bank_transfer', 'transfer', 'sample_receipt.png', 'successful', 1, '2026-04-05 14:30:00', 'Bank transfer confirmed in school Jaiz Bank account');

-- Payment 3: Pending verification payment claim for Maryam Danladi (₦15,000 remaining)
INSERT INTO `payments` (`id`, `invoice_id`, `student_id`, `parent_id`, `payment_reference`, `transaction_reference`, `amount`, `payment_method`, `payment_channel`, `proof_file`, `payment_status`, `notes`) VALUES
(3, 2, 2, 1, 'PAY-20260416-0003', 'GTB_TRF_8877112', 15000.00, 'bank_transfer', 'transfer', 'proof_transfer_sample.png', 'pending', 'Parent submitted transfer slip for pending balance');

-- RECEIPTS FOR CONFIRMED PAYMENTS
INSERT INTO `receipts` (`id`, `receipt_number`, `payment_id`, `invoice_id`, `student_id`, `parent_id`, `amount`, `issued_date`) VALUES
(1, 'REC-2026-0001', 1, 1, 1, 1, 39000.00, '2026-04-01'),
(2, 'REC-2026-0002', 2, 2, 2, 1, 20000.00, '2026-04-05');

-- NOTIFICATIONS & ANNOUNCEMENTS
INSERT INTO `notifications` (`id`, `sender_id`, `title`, `message`, `target_type`, `created_at`) VALUES
(1, 1, 'Welcome to the New Academic Term', 'Assalamu Alaikum wa Rahmatullah wa Barakatuh. We welcome all esteemed parents and students to the Third Term of the 2025/2026 academic session. Classes will commence promptly at 8:00 AM on Monday.', 'all', NOW()),
(2, 1, 'School Fee Payment Notice', 'Dear Parents, please be reminded that the deadline for Third Term fee payment is May 15th, 2026. Instalment payment options are available through the parent portal.', 'all', NOW());

-- NOTIFICATION RECIPIENTS
INSERT INTO `notification_recipients` (`notification_id`, `user_id`, `is_read`, `read_at`) VALUES
(1, 2, 1, NOW()),
(2, 2, 0, NULL),
(1, 3, 0, NULL),
(2, 3, 0, NULL);

-- AUDIT LOGS
INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`) VALUES
(1, 'SYSTEM_INITIALIZATION', 'system', 1, 'Darul Huda Islamiyya School Management System initialized successfully.', '127.0.0.1'),
(1, 'PAYMENT_VERIFIED', 'payment', 2, 'Verified bank transfer payment of ₦20,000 for student Maryam Danladi (ISMS/2026/002)', '127.0.0.1');
