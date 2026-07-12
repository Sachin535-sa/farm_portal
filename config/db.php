<?php
mysqli_report(MYSQLI_REPORT_OFF);

// Load database details from environment variables (for Render/Docker), falling back to defaults
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'farm_portal';
$port = getenv('DB_PORT') !== false ? intval(getenv('DB_PORT')) : null;

// Load production/custom database configuration if present (for local/manual overrides)
if (file_exists(__DIR__ . '/db_config.php')) {
    include __DIR__ . '/db_config.php';
}

if ($port !== null) {
    $conn = @mysqli_connect($host, $user, $pass, $db, $port);
} else {
    // Try port 3307 first
    $conn = @mysqli_connect($host, $user, $pass, $db, 3307);

    if (!$conn) {
        // Fall back to default port (3306)
        $conn = @mysqli_connect($host, $user, $pass, $db, 3306);
    }
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}


// ─────────────────────────────────────────────────────────
// CREATE BASIC TABLES IF NOT EXISTS
// ─────────────────────────────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    password VARCHAR(255),
    role VARCHAR(20) DEFAULT 'buyer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS crops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT,
    crop_name VARCHAR(100),
    quantity INT DEFAULT 0,
    price INT DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT,
    farmer_id INT,
    crop_id INT,
    quantity INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


// ─────────────────────────────────────────────────────────
// CROPS TABLE MIGRATIONS
// ─────────────────────────────────────────────────────────

$col_check1 = mysqli_query($conn,
    "SHOW COLUMNS FROM crops LIKE 'sustainability_badges'"
);

if (mysqli_num_rows($col_check1) == 0) {

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD sustainability_badges VARCHAR(255) DEFAULT ''"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD is_flagged TINYINT(1) DEFAULT 0"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD flag_reason VARCHAR(255) DEFAULT ''"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD reports_count INT DEFAULT 0"
    );
}


// ─────────────────────────────────────────────────────────
// ORDERS PAYMENT COLUMNS
// ─────────────────────────────────────────────────────────

$col_check2 = mysqli_query($conn,
    "SHOW COLUMNS FROM orders LIKE 'is_paid'"
);

if (mysqli_num_rows($col_check2) == 0) {

    mysqli_query($conn,
        "ALTER TABLE orders
        ADD is_paid TINYINT(1) DEFAULT 0"
    );

    mysqli_query($conn,
        "ALTER TABLE orders
        ADD payment_txn VARCHAR(100) DEFAULT ''"
    );
}


// ─────────────────────────────────────────────────────────
// ORDERS PRICE COLUMN
// ─────────────────────────────────────────────────────────

$col_check3 = mysqli_query($conn,
    "SHOW COLUMNS FROM orders LIKE 'price'"
);

if (mysqli_num_rows($col_check3) == 0) {

    mysqli_query($conn,
        "ALTER TABLE orders
        ADD price INT DEFAULT 0"
    );
}


// ─────────────────────────────────────────────────────────
// CROPS EXTRA PRICE COLUMNS
// ─────────────────────────────────────────────────────────

$col_check4 = mysqli_query($conn,
    "SHOW COLUMNS FROM crops LIKE 'expiry_date'"
);

if (mysqli_num_rows($col_check4) == 0) {

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD expiry_date DATE DEFAULT NULL"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD ref_bigbasket_price INT DEFAULT NULL"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD ref_reliance_price INT DEFAULT NULL"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD ref_mandi_price INT DEFAULT NULL"
    );
}


// ─────────────────────────────────────────────────────────
// AUCTION SYSTEM
// ─────────────────────────────────────────────────────────

$col_check5 = mysqli_query($conn,
    "SHOW COLUMNS FROM crops LIKE 'is_auction'"
);

if (mysqli_num_rows($col_check5) == 0) {

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD is_auction TINYINT(1) DEFAULT 0"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD auction_end_time DATETIME DEFAULT NULL"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD starting_bid INT DEFAULT 0"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD current_bid INT DEFAULT 0"
    );

    mysqli_query($conn,
        "ALTER TABLE crops
        ADD highest_bidder_id INT DEFAULT NULL"
    );
}


// ─────────────────────────────────────────────────────────
// BIDS TABLE
// ─────────────────────────────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bids (

    id INT AUTO_INCREMENT PRIMARY KEY,
    crop_id INT NOT NULL,
    buyer_id INT NOT NULL,
    bid_amount INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

)");


// ─────────────────────────────────────────────────────────
// CHAT MESSAGES TABLE
// ─────────────────────────────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS chat_messages (

    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    crop_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

)");


// ─────────────────────────────────────────────────────────
// BARGAINS TABLE
// ─────────────────────────────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bargains (

    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    farmer_id INT NOT NULL,
    crop_id INT NOT NULL,
    proposed_price INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

)");


// ─────────────────────────────────────────────────────────
// NOTIFICATIONS TABLE
// ─────────────────────────────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (

    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

)");


// ─────────────────────────────────────────────────────────
// COMPLAINTS TABLE
// ─────────────────────────────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS complaints (

    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    reason TEXT DEFAULT NULL,
    ai_result VARCHAR(100) DEFAULT NULL,
    damage_score FLOAT DEFAULT 0,
    fake_score FLOAT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

)");


// ─────────────────────────────────────────────────────────
// ORIGINAL PARCEL IMAGE COLUMN
// ─────────────────────────────────────────────────────────

$col_check_parcel = mysqli_query($conn,
    "SHOW COLUMNS FROM orders LIKE 'original_parcel_image'"
);

if (mysqli_num_rows($col_check_parcel) == 0) {

    mysqli_query($conn,
        "ALTER TABLE orders
        ADD original_parcel_image VARCHAR(255) DEFAULT NULL"
    );
}

// ── Add crop_image column to crops if missing ────────────────────────
$col_check_crop_img = mysqli_query($conn, "SHOW COLUMNS FROM `crops` LIKE 'crop_image'");
if ($col_check_crop_img && mysqli_num_rows($col_check_crop_img) == 0) {
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `crop_image` VARCHAR(255) DEFAULT NULL");
}

// ── Add rating_avg, review_count, and sales_volume columns to crops if missing ──
$col_check_trends = mysqli_query($conn, "SHOW COLUMNS FROM `crops` LIKE 'rating_avg'");
if ($col_check_trends && mysqli_num_rows($col_check_trends) == 0) {
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `rating_avg` DECIMAL(3,2) DEFAULT 4.50");
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `review_count` INT DEFAULT 5");
    mysqli_query($conn, "ALTER TABLE `crops` ADD COLUMN `sales_volume` INT DEFAULT 45");
}

// ── Add mobile_no, upi_id, and distributor columns to users if missing ──
$col_check_users_ext = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'mobile_no'");
if ($col_check_users_ext && mysqli_num_rows($col_check_users_ext) == 0) {
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `mobile_no` VARCHAR(20) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `upi_id` VARCHAR(100) DEFAULT NULL");
}

$col_check_users_dist = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'distributor_badge'");
if ($col_check_users_dist && mysqli_num_rows($col_check_users_dist) == 0) {
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `distributor_badge` VARCHAR(255) DEFAULT 'Standard Distributor'");
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `distributor_score` INT DEFAULT 85");
}

// ── Add state and district columns to users if missing ───────────────
$col_check_users_geo = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'state'");
if ($col_check_users_geo && mysqli_num_rows($col_check_users_geo) == 0) {
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `state` VARCHAR(100) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `district` VARCHAR(100) DEFAULT NULL");
}

// ── Auto-seed initial users if they do not exist ────────────────────
$user_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if ($user_table_check && mysqli_num_rows($user_table_check) > 0) {
    $user_exists_query = mysqli_query($conn, "SELECT * FROM users WHERE email = 'aniket@buyer.com' OR email = 'rajesh@farm.com'");
    if ($user_exists_query && mysqli_num_rows($user_exists_query) < 2) {
        // Seed default users (using IGNORE to prevent key collisions)
        mysqli_query($conn, "INSERT IGNORE INTO `users` (`id`, `name`, `email`, `password`, `role`, `distributor_badge`, `distributor_score`) VALUES
            (1, 'Rajesh Kumar (Farmer)', 'rajesh@farm.com', 'password123', 'farmer', '🥇 Certified Top-Tier Quality Distributor', 98),
            (2, 'Aniket Sharma (Buyer)', 'aniket@buyer.com', 'password123', 'buyer', 'Standard Distributor', 85),
            (3, 'Gurpreet Singh (Farmer)', 'gurpreet@farm.com', 'password123', 'farmer', '🌟 Highly Rated Eco-Grower', 95)");
            
        // Seed default crops
        mysqli_query($conn, "INSERT IGNORE INTO `crops` (`id`, `farmer_id`, `crop_name`, `price`, `quantity`, `rating_avg`, `review_count`, `sales_volume`) VALUES
            (1, 1, 'Organic Basmati Paddy (Rice)', 65, 450, 4.90, 28, 380),
            (2, 1, 'Kufri Jyoti Potatoes (Grade-A)', 22, 600, 4.60, 14, 190),
            (3, 1, 'Fresh Desi Red Tomatoes', 45, 150, 4.30, 9, 95),
            (4, 3, 'Premium Sharbati Wheat (Kanak)', 28, 800, 4.85, 36, 520),
            (5, 3, 'Organic Mustard Seeds (Sarson)', 85, 300, 4.70, 18, 140)");

        // Seed default orders
        mysqli_query($conn, "INSERT IGNORE INTO `orders` (`id`, `buyer_id`, `crop_id`, `quantity`, `status`) VALUES
            (1, 2, 1, 150, 'shipped'),
            (2, 2, 3, 50, 'delivered'),
            (3, 2, 4, 200, 'pending')");
    }
    
    // Unconditionally align passwords for demo purposes
    mysqli_query($conn, "UPDATE `users` SET `password` = 'password123' WHERE `email` = 'rajesh@farm.com'");
    mysqli_query($conn, "UPDATE `users` SET `password` = 'password123' WHERE `email` = 'aniket@buyer.com'");
    mysqli_query($conn, "UPDATE `users` SET `password` = 'password123' WHERE `email` = 'gurpreet@farm.com'");
}

// ─────────────────────────────────────────────────────────
// PAYMENTS TABLE MIGRATION
// ─────────────────────────────────────────────────────────
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `amount` INT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'success',
    `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Check/Add delivery columns to orders ─────────────────────────────
$col_check_del_partner = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE 'delivery_partner_id'");
if ($col_check_del_partner && mysqli_num_rows($col_check_del_partner) == 0) {
    mysqli_query($conn, "ALTER TABLE `orders` ADD COLUMN `delivery_partner_id` INT DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE `orders` ADD COLUMN `delivery_proof_image` VARCHAR(255) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE `orders` ADD COLUMN `package_type` VARCHAR(50) DEFAULT 'Standard'");
}

// ── Seed delivery partner if not exists ──────────────────────────────
$del_partner_check = mysqli_query($conn, "SELECT id FROM `users` WHERE `email` = 'vijay@delivery.com'");
if ($del_partner_check && mysqli_num_rows($del_partner_check) == 0) {
    mysqli_query($conn, "INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `mobile_no`, `distributor_badge`, `distributor_score`) VALUES
        (4, 'Vijay Kumar (Logistics)', 'vijay@delivery.com', 'password123', 'delivery_partner', '9876543210', 'Standard Partner', 95)");
} else {
    mysqli_query($conn, "UPDATE `users` SET `password` = 'password123' WHERE `email` = 'vijay@delivery.com'");
}

// ── Notification Renderer Helper ────────────────────────────────────
function get_notification_html($notif, $is_dashboard = false) {
    $msg = $notif['message'];
    
    // Determine category based on message content
    $type_class = '';
    if (stripos($msg, 'Payment Escrowed') !== false || stripos($msg, 'Payment Settled') !== false || stripos($msg, 'Escrowed') !== false || stripos($msg, 'Payment Verified') !== false) {
        $type_class = 'notif-payment';
    } elseif (stripos($msg, 'Warning') !== false || stripos($msg, 'Low Stock') !== false || stripos($msg, 'Suspicious') !== false) {
        $type_class = 'notif-warning';
    } elseif (stripos($msg, 'cancelled') !== false || stripos($msg, 'cancel') !== false) {
        $type_class = 'notif-cancel';
    } elseif (stripos($msg, 'Trade Finalized') !== false || stripos($msg, 'Successful') !== false || stripos($msg, 'successfully') !== false || stripos($msg, 'Verified') !== false) {
        $type_class = 'notif-success';
    }
    
    $unread_class = $notif['is_read'] == 0 ? 'unread' : '';
    $time_formatted = date("d M, h:i A", strtotime($notif['created_at']));
    
    if ($is_dashboard) {
        // Return formatting for dashboard page lists
        $style = "";
        if ($type_class == 'notif-payment') {
            $style = "border-left: 4px solid #6366f1; background: rgba(99, 102, 241, 0.04);";
        } elseif ($type_class == 'notif-warning') {
            $style = "border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.04);";
        } elseif ($type_class == 'notif-cancel') {
            $style = "border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.04);";
        } elseif ($type_class == 'notif-success') {
            $style = "border-left: 4px solid #10b981; background: rgba(16, 185, 129, 0.04);";
        } else {
            $style = "border-left: 4px solid #64748b; background: rgba(100, 116, 139, 0.04);";
        }
        
        $html = '<div style="' . $style . ' padding: 12px 16px; border-radius: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; gap: 12px; text-align: left;">';
        $html .= '  <div>';
        $html .= '    <div style="font-size: 13.5px; color: var(--dark); font-weight: 500;">' . $msg . '</div>';
        $html .= '    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">' . $time_formatted . '</div>';
        $html .= '  </div>';
        $html .= '</div>';
        return $html;
    } else {
        // Return formatting for navbar dropdown list
        $html = '<div class="notif-item ' . $type_class . ' ' . $unread_class . '">';
        $html .= '  <div class="notif-item-text">' . $msg . '</div>';
        $html .= '  <div class="notif-item-time">' . $time_formatted . '</div>';
        $html .= '</div>';
        return $html;
    }
}
?>