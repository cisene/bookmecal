-- Initial Schema Setup for Booking App

CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 30,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `booking_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT NOT NULL,
  `client_name` VARCHAR(255) NOT NULL,
  `client_email` VARCHAR(255) NOT NULL,
  `client_phone` VARCHAR(50) NOT NULL,
  `start_datetime` DATETIME NOT NULL,
  `appointment_status` ENUM('scheduled', 'approved', 'cancelled', 'late_no_show') NOT NULL DEFAULT 'scheduled',
  `cancellation_token` VARCHAR(64) UNIQUE NULL,
  `preferred_language` VARCHAR(10) NOT NULL DEFAULT 'sv',
  `client_timezone` VARCHAR(50) NOT NULL DEFAULT 'Europe/Stockholm',
  `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `gcal_event_id` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed initial treatment services
INSERT INTO `services` (`name`, `duration_minutes`, `price`) VALUES
('Medical Foot Care', 45, 750.00),
('Podiatry Consultation', 30, 500.00),
('Nail & Skin Treatment', 30, 450.00);