<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("config/db.php");

echo "<h3><i class='ph-duotone ph-plant'></i> Upgrading AgroNava Database Schema for Premium Membership</h3>";

// Check if tables exist and need updates
$table_check = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'reg_type'");
if (mysqli_num_rows($table_check) == 0) {
    // Columns do not exist, we need to add them!
    echo "<p>Adding Premium Membership compliance fields to `users` table...</p>";
    
    $alter_queries = [
        "ALTER TABLE `users` ADD COLUMN `reg_type` varchar(50) DEFAULT NULL AFTER `role`",
        "ALTER TABLE `users` ADD COLUMN `reg_level` varchar(50) DEFAULT NULL AFTER `reg_type`",
        "ALTER TABLE `users` ADD COLUMN `title` varchar(20) DEFAULT NULL AFTER `reg_level`",
        "ALTER TABLE `users` ADD COLUMN `first_name` varchar(100) DEFAULT NULL AFTER `title`",
        "ALTER TABLE `users` ADD COLUMN `middle_name` varchar(100) DEFAULT NULL AFTER `first_name`",
        "ALTER TABLE `users` ADD COLUMN `last_name` varchar(100) DEFAULT NULL AFTER `middle_name`",
        "ALTER TABLE `users` ADD COLUMN `gender` varchar(20) DEFAULT NULL AFTER `last_name`",
        "ALTER TABLE `users` ADD COLUMN `dob` varchar(20) DEFAULT NULL AFTER `gender`",
        "ALTER TABLE `users` ADD COLUMN `relation_type` varchar(20) DEFAULT NULL AFTER `dob`",
        "ALTER TABLE `users` ADD COLUMN `relation_name` varchar(100) DEFAULT NULL AFTER `relation_type`",
        "ALTER TABLE `users` ADD COLUMN `address` text DEFAULT NULL AFTER `relation_name`",
        "ALTER TABLE `users` ADD COLUMN `pincode` varchar(20) DEFAULT NULL AFTER `address`",
        "ALTER TABLE `users` ADD COLUMN `state` varchar(100) DEFAULT NULL AFTER `pincode`",
        "ALTER TABLE `users` ADD COLUMN `district` varchar(100) DEFAULT NULL AFTER `state`",
        "ALTER TABLE `users` ADD COLUMN `tehsil` varchar(100) DEFAULT NULL AFTER `district`",
        "ALTER TABLE `users` ADD COLUMN `city_village` varchar(100) DEFAULT NULL AFTER `tehsil`",
        "ALTER TABLE `users` ADD COLUMN `post` varchar(100) DEFAULT NULL AFTER `city_village`",
        "ALTER TABLE `users` ADD COLUMN `photo_id_type` varchar(50) DEFAULT NULL AFTER `post`",
        "ALTER TABLE `users` ADD COLUMN `photo_id_number` varchar(100) DEFAULT NULL AFTER `photo_id_type`",
        "ALTER TABLE `users` ADD COLUMN `mobile_no` varchar(20) DEFAULT NULL AFTER `photo_id_number`",
        "ALTER TABLE `users` ADD COLUMN `license_no` varchar(100) DEFAULT NULL AFTER `mobile_no`",
        "ALTER TABLE `users` ADD COLUMN `ifsc_code` varchar(20) DEFAULT NULL AFTER `license_no`",
        "ALTER TABLE `users` ADD COLUMN `bank_holder_name` varchar(100) DEFAULT NULL AFTER `ifsc_code`",
        "ALTER TABLE `users` ADD COLUMN `bank_name` varchar(100) DEFAULT NULL AFTER `bank_holder_name`",
        "ALTER TABLE `users` ADD COLUMN `bank_account_no` varchar(50) DEFAULT NULL AFTER `bank_name`",
        "ALTER TABLE `users` ADD COLUMN `branch_name` varchar(100) DEFAULT NULL AFTER `bank_account_no`",
        "ALTER TABLE `users` ADD COLUMN `branch_address` text DEFAULT NULL AFTER `branch_name`",
        "ALTER TABLE `users` ADD COLUMN `upi_id` varchar(100) DEFAULT NULL AFTER `branch_address`",
        "ALTER TABLE `users` ADD COLUMN `passbook_image` varchar(255) DEFAULT NULL AFTER `upi_id`",
        "ALTER TABLE `users` ADD COLUMN `id_proof_image` varchar(255) DEFAULT NULL AFTER `passbook_image`",
        "ALTER TABLE `users` ADD COLUMN `get_sms` tinyint(1) DEFAULT 0 AFTER `id_proof_image`",
        "ALTER TABLE `users` ADD COLUMN `get_email` tinyint(1) DEFAULT 0 AFTER `get_sms`"
    ];

    foreach ($alter_queries as $query) {
        if (mysqli_query($conn, $query)) {
            echo "<i class='ph-duotone ph-check-circle'></i> Query executed: " . substr($query, 0, 45) . "...<br>";
        } else {
            echo "<i class='ph-duotone ph-x-circle'></i> Query failed: " . mysqli_error($conn) . "<br>";
        }
    }
    echo "<i class='ph-duotone ph-party-popper'></i> <strong>Alteration completed successfully!</strong>";
} else {
    echo "ℹ️ columns already exist in `users` table. No updates needed.";
}

// Let's also check if we can add an image column to `crops` table to store product images, just in case!
$crop_check = mysqli_query($conn, "SHOW COLUMNS FROM `crops` LIKE 'crop_image'");
if (mysqli_num_rows($crop_check) == 0) {
    echo "<p>Adding `crop_image` column to `crops` table...</p>";
    if (mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `crop_image` varchar(255) DEFAULT NULL")) {
        echo "<i class='ph-duotone ph-check-circle'></i> `crop_image` column added to `crops` table successfully!<br>";
    } else {
        echo "<i class='ph-duotone ph-x-circle'></i> Failed to add `crop_image` column: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "ℹ️ `crop_image` column already exists in `crops` table.<br>";
}

// 1. Check and add distributor quality fields to `users`
$dist_badge_check = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'distributor_badge'");
if (mysqli_num_rows($dist_badge_check) == 0) {
    echo "<p>Adding distributor quality fields (`distributor_badge`, `distributor_score`) to `users` table...</p>";
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `distributor_badge` varchar(255) DEFAULT 'Standard Distributor'");
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `distributor_score` int(11) DEFAULT 85");
    echo "<i class='ph-duotone ph-check-circle'></i> Distributor quality columns added.<br>";
}

// 2. Check and add rating, review, and sales trend fields to `crops`
$crop_trend_check = mysqli_query($conn, "SHOW COLUMNS FROM `crops` LIKE 'rating_avg'");
if (mysqli_num_rows($crop_trend_check) == 0) {
    echo "<p>Adding ratings and sales trend fields (`rating_avg`, `review_count`, `sales_volume`) to `crops` table...</p>";
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `rating_avg` decimal(3,2) DEFAULT 4.50");
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `review_count` int(11) DEFAULT 5");
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `sales_volume` int(11) DEFAULT 45");
    echo "<i class='ph-duotone ph-check-circle'></i> Crop ratings and sales trend columns added.<br>";
}

// 3. Seed realistic high-performance quality stats for demo purposes
echo "<p>Seeding premium rating trends and distributor quality scores for demo accounts...</p>";
mysqli_query($conn, "UPDATE `users` SET `distributor_badge` = '🥇 Certified Top-Tier Quality Distributor', `distributor_score` = 98 WHERE `id` = 1");
mysqli_query($conn, "UPDATE `users` SET `distributor_badge` = '🌟 Highly Rated Eco-Grower', `distributor_score` = 95 WHERE `id` = 3");

// Crop seed data
mysqli_query($conn, "UPDATE `crops` SET `rating_avg` = 4.90, `review_count` = 28, `sales_volume` = 380 WHERE `id` = 1");
mysqli_query($conn, "UPDATE `crops` SET `rating_avg` = 4.60, `review_count` = 14, `sales_volume` = 190 WHERE `id` = 2");
mysqli_query($conn, "UPDATE `crops` SET `rating_avg` = 4.30, `review_count` = 9, `sales_volume` = 95 WHERE `id` = 3");
mysqli_query($conn, "UPDATE `crops` SET `rating_avg` = 4.85, `review_count` = 36, `sales_volume` = 520 WHERE `id` = 4");
mysqli_query($conn, "UPDATE `crops` SET `rating_avg` = 4.70, `review_count` = 18, `sales_volume` = 140 WHERE `id` = 5");

// --- START OF NEW SCHEMAS FOR EXPIRY, COMPARISONS, AND PERSISTENT BARGAINING ---
echo "<h3><i class='ph-duotone ph-leaf'></i> Upgrading AgroNava Database with Premium Expiry, Comparison & Bargaining Ledger...</h3>";

// 1. Alter crops table for Expiry Date and Supermarket Reference Prices
$crops_expiry_check = mysqli_query($conn, "SHOW COLUMNS FROM `crops` LIKE 'expiry_date'");
if (mysqli_num_rows($crops_expiry_check) == 0) {
    echo "<p>Adding `expiry_date` DATE column to `crops` table...</p>";
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `expiry_date` DATE DEFAULT NULL");
    echo "<i class='ph-duotone ph-check-circle'></i> `expiry_date` column added.<br>";
}

$crops_ref_rel_check = mysqli_query($conn, "SHOW COLUMNS FROM `crops` LIKE 'ref_reliance_price'");
if (mysqli_num_rows($crops_ref_rel_check) == 0) {
    echo "<p>Adding reference comparison price columns (`ref_reliance_price`, `ref_bigbasket_price`, `ref_mandi_price`) to `crops` table...</p>";
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `ref_reliance_price` int(11) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `ref_bigbasket_price` int(11) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `ref_mandi_price` int(11) DEFAULT NULL");
    echo "<i class='ph-duotone ph-check-circle'></i> Price comparison columns added.<br>";
}

// Seed realistic future expiry dates and comparison references for demo crops
echo "<p>Seeding realistic expiry dates and supermarket reference benchmark rates for demo produce...</p>";
$today = date("Y-m-d");
$date_5d = date("Y-m-d", strtotime("+5 days"));
$date_8d = date("Y-m-d", strtotime("+8 days"));
$date_10d = date("Y-m-d", strtotime("+10 days"));
$date_1d = date("Y-m-d", strtotime("+1 day")); // tomatoes expire fast!

mysqli_query($conn, "UPDATE `crops` SET `expiry_date` = '$date_8d', `ref_reliance_price` = 85, `ref_bigbasket_price` = 90, `ref_mandi_price` = 58 WHERE `id` = 1"); // rice
mysqli_query($conn, "UPDATE `crops` SET `expiry_date` = '$date_10d', `ref_reliance_price` = 30, `ref_bigbasket_price` = 32, `ref_mandi_price` = 18 WHERE `id` = 2"); // potatoes
mysqli_query($conn, "UPDATE `crops` SET `expiry_date` = '$date_1d', `ref_reliance_price` = 60, `ref_bigbasket_price` = 62, `ref_mandi_price` = 38 WHERE `id` = 3"); // tomatoes
mysqli_query($conn, "UPDATE `crops` SET `expiry_date` = '$date_10d', `ref_reliance_price` = 38, `ref_bigbasket_price` = 40, `ref_mandi_price` = 24 WHERE `id` = 4"); // wheat
mysqli_query($conn, "UPDATE `crops` SET `expiry_date` = '$date_8d', `ref_reliance_price` = 110, `ref_bigbasket_price` = 115, `ref_mandi_price` = 78 WHERE `id` = 5"); // mustard seeds
echo "<i class='ph-duotone ph-check-circle'></i> Seeding comparison data completed.<br>";

// 2. Alter orders table to include unit price tracking for historical accuracy and bargaining
$orders_price_check = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE 'price'");
if (mysqli_num_rows($orders_price_check) == 0) {
    echo "<p>Adding `price` column to `orders` table for historical audit safety...</p>";
    mysqli_query($conn, "ALTER TABLE `orders` ADD COLUMN `price` int(11) NOT NULL DEFAULT 0 AFTER `quantity`");
    echo "<i class='ph-duotone ph-check-circle'></i> `price` column added to `orders`. Running order price backfill migration...<br>";
    
    // Backfill historical order prices from crops table
    $backfill_query = "UPDATE `orders` o JOIN `crops` c ON o.crop_id = c.id SET o.price = c.price WHERE o.price = 0";
    if (mysqli_query($conn, $backfill_query)) {
        echo "<i class='ph-duotone ph-check-circle'></i> Order prices backfilled successfully.<br>";
    } else {
        echo "<i class='ph-duotone ph-x-circle'></i> Backfill failed: " . mysqli_error($conn) . "<br>";
    }
}

// 3. Create persistent chat messaging ledger
echo "<p>Establishing `chat_messages` table...</p>";
$chat_msg_sql = "CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sender_id` int(11) NOT NULL,
    `receiver_id` int(11) NOT NULL,
    `crop_id` int(11) NOT NULL,
    `message` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if (mysqli_query($conn, $chat_msg_sql)) {
    echo "<i class='ph-duotone ph-check-circle'></i> `chat_messages` ledger established.<br>";
} else {
    echo "<i class='ph-duotone ph-x-circle'></i> Failed creating `chat_messages` table: " . mysqli_error($conn) . "<br>";
}

// 4. Create persistent bargaining ledger
echo "<p>Establishing `bargains` table...</p>";
$bargains_sql = "CREATE TABLE IF NOT EXISTS `bargains` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `buyer_id` int(11) NOT NULL,
    `farmer_id` int(11) NOT NULL,
    `crop_id` int(11) NOT NULL,
    `proposed_price` int(11) NOT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if (mysqli_query($conn, $bargains_sql)) {
    echo "<i class='ph-duotone ph-check-circle'></i> `bargains` ledger established.<br>";
} else {
    echo "<i class='ph-duotone ph-x-circle'></i> Failed creating `bargains` table: " . mysqli_error($conn) . "<br>";
}

// 5. Create persistent notifications ledger
echo "<p>Establishing `notifications` table...</p>";
$notif_sql = "CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `message` text NOT NULL,
    `is_read` tinyint(1) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if (mysqli_query($conn, $notif_sql)) {
    echo "<i class='ph-duotone ph-check-circle'></i> `notifications` ledger established.<br>";
} else {
    echo "<i class='ph-duotone ph-x-circle'></i> Failed creating `notifications` table: " . mysqli_error($conn) . "<br>";
}

// 6. Add AgriDirect Advanced Delivery and Transparency Fields
echo "<h3><i class='ph-duotone ph-truck'></i> Upgrading AgroNava Database with AgriDirect Delivery Features...</h3>";

$orders_agri_check = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE 'transport_cost'");
if (mysqli_num_rows($orders_agri_check) == 0) {
    echo "<p>Adding AgriDirect Delivery fields to `orders` table...</p>";
    $order_alters = [
        "ALTER TABLE `orders` ADD COLUMN `transport_cost` int(11) NOT NULL DEFAULT 0 AFTER `status`",
        "ALTER TABLE `orders` ADD COLUMN `delivery_otp` varchar(10) DEFAULT NULL AFTER `transport_cost`",
        "ALTER TABLE `orders` ADD COLUMN `tracking_status` varchar(50) DEFAULT 'Preparing' AFTER `delivery_otp`",
        "ALTER TABLE `orders` ADD COLUMN `qr_code_hash` varchar(255) DEFAULT NULL AFTER `tracking_status`",
        "ALTER TABLE `orders` ADD COLUMN `distance_km` decimal(5,2) DEFAULT 0.00 AFTER `qr_code_hash`",
        "ALTER TABLE `orders` ADD COLUMN `weight_kg` decimal(5,2) DEFAULT 0.00 AFTER `distance_km`"
    ];
    foreach ($order_alters as $q) {
        if(mysqli_query($conn, $q)) {
            echo "<i class='ph-duotone ph-check-circle'></i> Query executed: " . substr($q, 0, 50) . "...<br>";
        } else {
            echo "<i class='ph-duotone ph-x-circle'></i> Query failed: " . mysqli_error($conn) . "<br>";
        }
    }
} else {
    echo "ℹ️ AgriDirect delivery columns already exist in `orders`.<br>";
}

$crops_agri_check = mysqli_query($conn, "SHOW COLUMNS FROM `crops` LIKE 'harvest_date'");
if (mysqli_num_rows($crops_agri_check) == 0) {
    echo "<p>Adding AgriDirect Transparency fields to `crops` table...</p>";
    $crop_alters = [
        "ALTER TABLE `crops` ADD COLUMN `harvest_date` date DEFAULT NULL",
        "ALTER TABLE `crops` ADD COLUMN `quality_grade` varchar(50) DEFAULT NULL",
        "ALTER TABLE `crops` ADD COLUMN `is_organic` tinyint(1) DEFAULT 0"
    ];
    foreach ($crop_alters as $q) {
        if(mysqli_query($conn, $q)) {
            echo "<i class='ph-duotone ph-check-circle'></i> Query executed: " . substr($q, 0, 50) . "...<br>";
        } else {
            echo "<i class='ph-duotone ph-x-circle'></i> Query failed: " . mysqli_error($conn) . "<br>";
        }
    }
} else {
    echo "ℹ️ AgriDirect transparency columns already exist in `crops`.<br>";
}

$users_upi_check = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'upi_id'");
if (mysqli_num_rows($users_upi_check) == 0) {
    echo "<p>Adding `upi_id` field to `users` table...</p>";
    if(mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `upi_id` varchar(100) DEFAULT NULL")) {
        echo "<i class='ph-duotone ph-check-circle'></i> Query executed: ALTER TABLE users ADD COLUMN upi_id...<br>";
    } else {
        echo "<i class='ph-duotone ph-x-circle'></i> Query failed: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "ℹ️ `upi_id` column already exists in `users`.<br>";
}

echo "<i class='ph-duotone ph-party-popper'></i> <strong>Database upgrade successfully completed!</strong>";
?>

