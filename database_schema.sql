
-- Room Rental Management System
-- Database schema


CREATE DATABASE IF NOT EXISTS `room_rental_system`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `room_rental_system`;

-- =====================================================================
-- 1. Users
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,                    -- bcrypt hash
  `role` ENUM('admin','landlord','tenant') NOT NULL DEFAULT 'tenant',
  `verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `profile_picture` VARCHAR(255) DEFAULT NULL,           -- path to uploaded profile picture
  `citizenship_front` VARCHAR(255) DEFAULT NULL,         -- path to citizenship front image
  `citizenship_back` VARCHAR(255) DEFAULT NULL,          -- path to citizenship back image
  `citizenship_status` ENUM('pending','approved','rejected') DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 2. Rooms
-- =====================================================================
CREATE TABLE IF NOT EXISTS `rooms` (
  `room_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `landlord_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `rent_amount` DECIMAL(10,2) NOT NULL,
  `rent_type` ENUM('monthly','weekly') NOT NULL DEFAULT 'monthly',
  `room_type` ENUM('single','shared','apartment','studio') NOT NULL DEFAULT 'single',
  `capacity` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `availability_status` ENUM('available','booked','maintenance') NOT NULL DEFAULT 'available',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`landlord_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 3. Room images
-- =====================================================================
CREATE TABLE IF NOT EXISTS `room_images` (
  `image_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 4. Room facilities
-- =====================================================================
CREATE TABLE IF NOT EXISTS `room_facilities` (
  `facility_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT UNSIGNED NOT NULL,
  `facility_name` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 5. Bookings
-- =====================================================================
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `room_id` INT UNSIGNED NOT NULL,
  `move_in_date` DATE NOT NULL,
  `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `cancellation_reason` TEXT NULL, -- reason provided by the tenant when cancelling
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`tenant_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- NOTE: If the `bookings` table already exists in your database (i.e. you
-- created it before the cancellation_reason column was added), run this
-- once in phpMyAdmin / MySQL to add the new column:
--   ALTER TABLE `bookings` ADD COLUMN `cancellation_reason` TEXT NULL AFTER `status`;
-- =====================================================================

-- =====================================================================
-- 6. Favorites (tenant saves a room)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `favorites` (
  `favorite_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `room_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_user_room` (`user_id`,`room_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 7. Reviews
-- =====================================================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT UNSIGNED NOT NULL,
  `tenant_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`room_id`) ON DELETE CASCADE,
  FOREIGN KEY (`tenant_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 8. Complaints
-- =====================================================================
CREATE TABLE IF NOT EXISTS `complaints` (
  `complaint_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `room_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`tenant_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 9. Notifications
-- =====================================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- Seed data — demo accounts
-- =====================================================================
-- Passwords: admin123 / landlord123 / tenant123  (bcrypt hashes)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `role`, `verification_status`, `created_at`)
VALUES
(
  'System Admin',
  'admin@roomrental.com',
  '+977-9800000000',
  '$2y$10$lBfH5HWFY8gtDHGSkTHtDOHjRAh87ZF3E6ShcCMeU5Swem93M/IhC',
  'admin',
  'approved',
  NOW()
),
(
  'Ram Thapa',
  'landlord@demo.com',
  '+977-9812345678',
  '$2y$10$7DMHFGwAZ/NN4/Xg4qCZ9OnocHb49WMwpz.CxX1dlaIzNQjKocSx6',
  'landlord',
  'approved',
  NOW()
),
(
  'Sita Rai',
  'tenant@demo.com',
  '+977-9845678901',
  '$2y$10$cj9L9Is03IrnxzNalcoNwe52pZupxDhkHnoR1fWQtuHJ11jqmaty6',
  'tenant',
  'approved',
  NOW()
);
