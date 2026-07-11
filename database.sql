-- AgroNava Database Setup Script
-- Use this file to import the database structure and sample data into phpMyAdmin (MySQL).

-- (Database creation statements removed for compatibility with shared hosting)

-- --------------------------------------------------------

-- 2. Structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL,
  -- Premium Direct Trade Columns
  `reg_type` varchar(50) DEFAULT NULL,
  `reg_level` varchar(50) DEFAULT NULL,
  `title` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` varchar(20) DEFAULT NULL,
  `relation_type` varchar(20) DEFAULT NULL,
  `relation_name` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `tehsil` varchar(100) DEFAULT NULL,
  `city_village` varchar(100) DEFAULT NULL,
  `post` varchar(100) DEFAULT NULL,
  `photo_id_type` varchar(50) DEFAULT NULL,
  `photo_id_number` varchar(100) DEFAULT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `license_no` varchar(100) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `bank_holder_name` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `branch_address` text DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `passbook_image` varchar(255) DEFAULT NULL,
  `id_proof_image` varchar(255) DEFAULT NULL,
  `get_sms` tinyint(1) DEFAULT 0,
  `get_email` tinyint(1) DEFAULT 0,
  `distributor_badge` varchar(255) DEFAULT 'Standard Distributor',
  `distributor_score` int(11) DEFAULT 85,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 3. Structure for table `crops`
CREATE TABLE IF NOT EXISTS `crops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `crop_name` varchar(100) NOT NULL,
  `price` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `crop_image` varchar(255) DEFAULT NULL,
  `rating_avg` decimal(3,2) DEFAULT 4.50,
  `review_count` int(11) DEFAULT 5,
  `sales_volume` int(11) DEFAULT 45,
  `harvest_date` date DEFAULT NULL,
  `quality_grade` varchar(50) DEFAULT NULL,
  `is_organic` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `farmer_id` (`farmer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 4. Structure for table `orders`
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `transport_cost` int(11) NOT NULL DEFAULT 0,
  `delivery_otp` varchar(10) DEFAULT NULL,
  `tracking_status` varchar(50) DEFAULT 'Preparing',
  `qr_code_hash` varchar(255) DEFAULT NULL,
  `distance_km` decimal(5,2) DEFAULT 0.00,
  `weight_kg` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `crop_id` (`crop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 5. Insert High-Quality Competition Demo Data
-- Default passwords are set to "password123" for simplicity during demonstration.

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `distributor_badge`, `distributor_score`) VALUES
(1, 'Rajesh Kumar (Farmer)', 'rajesh@farm.com', 'password123', 'farmer', '🥇 Certified Top-Tier Quality Distributor', 98),
(2, 'Aniket Sharma (Buyer)', 'aniket@buyer.com', 'password123', 'buyer', 'Standard Distributor', 85),
(3, 'Gurpreet Singh (Farmer)', 'gurpreet@farm.com', 'password123', 'farmer', '🌟 Highly Rated Eco-Grower', 95);

INSERT INTO `crops` (`id`, `farmer_id`, `crop_name`, `price`, `quantity`, `crop_image`, `rating_avg`, `review_count`, `sales_volume`) VALUES
(1, 1, 'Organic Basmati Paddy (Rice)', 65, 450, NULL, 4.90, 28, 380),
(2, 1, 'Kufri Jyoti Potatoes (Grade-A)', 22, 600, NULL, 4.60, 14, 190),
(3, 1, 'Fresh Desi Red Tomatoes', 45, 150, NULL, 4.30, 9, 95),
(4, 3, 'Premium Sharbati Wheat (Kanak)', 28, 800, NULL, 4.85, 36, 520),
(5, 3, 'Organic Mustard Seeds (Sarson)', 85, 300, NULL, 4.70, 18, 140);

INSERT INTO `orders` (`id`, `buyer_id`, `crop_id`, `quantity`, `status`) VALUES
(1, 2, 1, 150, 'shipped'),
(2, 2, 3, 50, 'delivered'),
(3, 2, 4, 200, 'pending');

-- --------------------------------------------------------

-- 6. Add Foreign Key Constraints for database referential integrity
ALTER TABLE `crops`
  ADD CONSTRAINT `fk_crop_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;
COMMIT;
