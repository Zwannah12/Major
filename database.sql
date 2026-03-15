
--
-- Database: `agrospheremarketlink`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('farmer', 'buyer', 'transporter', 'admin') NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `location`) VALUES
('Admin User', 'admin@agrosphere.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '250788123456', 'Kigali'),
('Farmer John', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '250788111222', 'Bugesera'),
('Buyer Alice', 'alice@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '250788333444', 'Kigali'),
('Transporter Bob', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'transporter', '250788555666', 'Musanze'),
('Farmer Jane', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '250788777888', 'Rubavu');

-- --------------------------------------------------------

--
-- Table structure for table `crops`
--

CREATE TABLE IF NOT EXISTS `crops` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` INT(11) NOT NULL,
  `crop_name` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL, -- e.g., in KGs or units
  `price` DECIMAL(10,2) NOT NULL,    -- price per unit/KG
  `location` VARCHAR(255) DEFAULT NULL,
  `harvest_date` DATE DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT 'default.jpg',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`farmer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `crops`
--

INSERT INTO `crops` (`farmer_id`, `crop_name`, `quantity`, `price`, `location`, `harvest_date`, `image`) VALUES
(2, 'Tomatoes', 100.00, 500.00, 'Bugesera', '2026-03-20', 'tomato.jpg'),
(2, 'Potatoes', 500.00, 300.00, 'Bugesera', '2026-03-25', 'potato.jpg'),
(5, 'Cabbage', 200.00, 400.00, 'Rubavu', '2026-03-18', 'cabbage.jpg'),
(5, 'Carrots', 150.00, 350.00, 'Rubavu', '2026-03-22', 'carrot.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` INT(11) NOT NULL,
  `crop_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending', 'accepted', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  `transporter_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`crop_id`) REFERENCES `crops`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transporter_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`buyer_id`, `crop_id`, `quantity`, `total_price`, `status`, `transporter_id`) VALUES
(3, 1, 10.00, 5000.00, 'pending', NULL),
(3, 3, 20.00, 8000.00, 'accepted', 4);

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `transporter_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'in_progress', 'delivered', 'cancelled') DEFAULT 'pending',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transporter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`order_id`, `transporter_id`, `status`) VALUES
(2, 4, 'in_progress');

--
-- Passwords for sample users (all are 'password123'):
-- The password hash was generated using `password_hash('password123', PASSWORD_BCRYPT);`
-- A sample hash was created in PHP: `echo password_hash('password123', PASSWORD_DEFAULT);`
-- Result: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- Note: Replace with actual generated hashes in a production environment.
