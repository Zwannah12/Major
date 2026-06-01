--
-- Database: `agrospheremarketlink`
--

-- --------------------------------------------------------
-- USERS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('farmer', 'buyer', 'transporter', 'admin') NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `profile_image` VARCHAR(255) DEFAULT 'user_avatar.png',
  `verified` BOOLEAN DEFAULT FALSE,
  `is_verified` BOOLEAN DEFAULT FALSE,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `two_factor_enabled` BOOLEAN DEFAULT FALSE,
  `two_factor_token` VARCHAR(255) DEFAULT NULL,
  `two_factor_token_expires` TIMESTAMP NULL DEFAULT NULL,
  `reset_token` VARCHAR(255) DEFAULT NULL,
  `token_expiry` TIMESTAMP NULL DEFAULT NULL,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `last_login_ip` VARCHAR(45) DEFAULT NULL,
  `phone_verified` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `location`, `verified`, `is_verified`, `email_verified_at`, `profile_image`) VALUES
('Admin User', 'admin@agrosphere.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '250788123456', 'Kigali', TRUE, TRUE, NOW(), 'user_avatar.png'),
('Farmer John', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '250788111222', 'Bugesera', TRUE, TRUE, NOW(), 'user_avatar.png'),
('Buyer Alice', 'alice@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '250788333444', 'Kigali', TRUE, TRUE, NOW(), 'user_avatar.png'),
('Transporter Bob', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'transporter', '250788555666', 'Musanze', TRUE, TRUE, NOW(), 'user_avatar.png'),
('Farmer Jane', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '250788777888', 'Rubavu', TRUE, TRUE, NOW(), 'user_avatar.png');

-- --------------------------------------------------------
-- CROPS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `crops` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` INT(11) NOT NULL,
  `crop_name` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `harvest_date` DATE DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT 'default_crop.jpg',
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
  `search_vector` VARCHAR(1000) DEFAULT NULL,
  `view_count` INT(11) DEFAULT 0,
  `avg_rating` DECIMAL(3,2) DEFAULT 0,
  `review_count` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_farmer_id` (`farmer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`farmer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `crops` (`farmer_id`, `crop_name`, `quantity`, `price`, `location`, `harvest_date`, `image`, `status`) VALUES
(2, 'Tomatoes', 100.00, 500.00, 'Bugesera', '2026-03-20', 'tomato.jpg', 'approved'),
(2, 'Potatoes', 500.00, 300.00, 'Bugesera', '2026-03-25', 'potato.jpg', 'approved'),
(5, 'Cabbage', 200.00, 400.00, 'Rubavu', '2026-03-18', 'cabbage.jpg', 'approved'),
(5, 'Carrots', 150.00, 350.00, 'Rubavu', '2026-03-22', 'carrot.jpg', 'approved');

-- --------------------------------------------------------
-- ORDERS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` INT(11) NOT NULL,
  `crop_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending', 'accepted', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  `payment_status` VARCHAR(50) DEFAULT 'unpaid' NOT NULL,
  `transporter_id` INT(11) DEFAULT NULL,
  `estimated_delivery` TIMESTAMP NULL DEFAULT NULL,
  `actual_delivery` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_buyer_id` (`buyer_id`),
  KEY `idx_crop_id` (`crop_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`crop_id`) REFERENCES `crops`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transporter_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `orders` (`buyer_id`, `crop_id`, `quantity`, `total_price`, `status`, `payment_status`, `transporter_id`) VALUES
(3, 1, 10.00, 5000.00, 'pending', 'unpaid', NULL),
(3, 3, 20.00, 8000.00, 'accepted', 'paid', 4);

-- --------------------------------------------------------
-- DELIVERIES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `transporter_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'in_progress', 'delivered', 'cancelled') DEFAULT 'pending',
  `current_latitude` DECIMAL(10,8) DEFAULT NULL,
  `current_longitude` DECIMAL(11,8) DEFAULT NULL,
  `estimated_arrival` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_transporter_id` (`transporter_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transporter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `deliveries` (`order_id`, `transporter_id`, `status`) VALUES
(2, 4, 'in_progress');

-- --------------------------------------------------------
-- MESSAGES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'replied') DEFAULT 'unread',
  `admin_reply` TEXT DEFAULT NULL,
  `replied_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- DIRECT MESSAGES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `direct_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sender_id` INT(11) NOT NULL,
  `receiver_id` INT(11) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_receiver_id` (`receiver_id`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- NOTIFICATIONS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT '#',
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- ANNOUNCEMENTS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `target_audience` VARCHAR(255) NOT NULL DEFAULT 'farmer,buyer,transporter,public',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `announcements` (`title`, `message`, `target_audience`) VALUES
('Welcome to AgroSphere', 'Fresh listings, trusted sellers, and faster delivery coordination are now available.', 'farmer,buyer,transporter,public');

-- --------------------------------------------------------
-- REVIEWS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reviewer_id` INT(11) NOT NULL,
  `reviewed_user_id` INT(11) NOT NULL,
  `crop_id` INT(11) DEFAULT NULL,
  `order_id` INT(11) DEFAULT NULL,
  `rating` INT(1) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `comment` TEXT DEFAULT NULL,
  `review_type` ENUM('product', 'seller', 'transporter') NOT NULL,
  `helpful_count` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviewer_id` (`reviewer_id`),
  KEY `idx_reviewed_user_id` (`reviewed_user_id`),
  KEY `idx_crop_id` (`crop_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_review_type` (`review_type`),
  FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`crop_id`) REFERENCES `crops`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- PAYMENTS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'RWF',
  `payment_method` ENUM('mtn_money', 'airtel_money', 'bank_transfer', 'cash_on_delivery') NOT NULL,
  `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
  `transaction_id` VARCHAR(255) UNIQUE,
  `reference_number` VARCHAR(255) UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `metadata` JSON,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- INVOICES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) DEFAULT 0,
  `discount_amount` DECIMAL(10,2) DEFAULT 0,
  `status` ENUM('draft', 'sent', 'viewed', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
  `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `due_at` TIMESTAMP NULL DEFAULT NULL,
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- ACTIVITY LOGS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action` (`action`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- SECURITY LOGS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `severity` ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
  `user_id` INT(11) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- EMAIL VERIFICATIONS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `verified` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- SEARCH HISTORY
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `search_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `search_query` VARCHAR(255) DEFAULT NULL,
  `filters` JSON DEFAULT NULL,
  `results_count` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- WISHLISTS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `wishlists` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `crop_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_crop` (`user_id`, `crop_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`crop_id`) REFERENCES `crops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
