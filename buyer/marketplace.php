<?php
session_start();
include("../config/db.php");

// Session check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer"){
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// Fetch notifications
$user_id_clean = mysqli_real_escape_string($conn, $buyer_id);
$notif_query = "SELECT * FROM notifications WHERE user_id = '$user_id_clean' ORDER BY created_at DESC LIMIT 5";
$notif_res = mysqli_query($conn, $notif_query);
$unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$user_id_clean' AND is_read = 0";
$unread_count_res = mysqli_query($conn, $unread_count_query);
$unread_count = 0;
if ($unread_count_res) {
    $unread_count_row = mysqli_fetch_assoc($unread_count_res);
    $unread_count = (int)$unread_count_row['count'];
}

// Fetch crop listings with Farmer name to show connection and quality metrics (Phase 13)
$sql = "SELECT c.*, u.name as farmer_name, u.email as farmer_email, u.distributor_badge, u.distributor_score, b.proposed_price as bargained_price 
        FROM crops c 
        JOIN users u ON c.farmer_id = u.id 
        LEFT JOIN bargains b ON b.crop_id = c.id AND b.buyer_id = '$buyer_id' AND b.status = 'accepted'
        WHERE c.quantity > 0 AND (c.expiry_date >= CURDATE() OR c.expiry_date IS NULL) AND c.reports_count < 3";
$result = mysqli_query($conn, $sql);

// Query top selling harvests using trend method (sales volume + high rating)
$sql_trending = "SELECT c.*, u.name as farmer_name, u.email as farmer_email, u.distributor_badge, u.distributor_score, b.proposed_price as bargained_price 
                 FROM crops c 
                 JOIN users u ON c.farmer_id = u.id 
                 LEFT JOIN bargains b ON b.crop_id = c.id AND b.buyer_id = '$buyer_id' AND b.status = 'accepted'
                 WHERE c.quantity > 0 AND (c.expiry_date >= CURDATE() OR c.expiry_date IS NULL) AND c.reports_count < 3
                 ORDER BY c.sales_volume DESC, c.rating_avg DESC 
                 LIMIT 3";
$result_trending = mysqli_query($conn, $sql_trending);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produce Marketplace | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .marketplace-hero {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            padding: 48px 40px;
            border-radius: var(--radius-lg);
            color: white;
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-md);
        }
        
        .crop-visual-badge {
            width: 100%;
            height: 140px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(15, 118, 110, 0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 54px;
            margin-bottom: 16px;
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        
        /* Interactive Search Layout */
        .search-container {
            display: flex;
            gap: 16px;
            margin-bottom: 28px;
            width: 100%;
        }
        
        .search-bar {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 15px;
            outline: none;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-glow);
        }

        /* Trending Ribbon and Badge styling */
        .trending-section {
            margin-bottom: 48px;
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.03), transparent);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(16, 185, 129, 0.08);
        }
        .trending-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 16px;
        }
        .trending-card {
            border: 2px solid rgba(16, 185, 129, 0.15) !important;
            background: rgba(255, 255, 255, 0.85);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md) !important;
        }
        .trending-card::before {
            content: "🔥 TOP SELLER";
            position: absolute;
            top: 12px;
            left: -32px;
            background: var(--primary-hover);
            color: white;
            font-size: 8.5px;
            font-weight: 800;
            padding: 4px 32px;
            transform: rotate(-30deg);
            z-index: 10;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .distributor-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--secondary);
            background: rgba(15, 118, 110, 0.06);
            padding: 6px 12px;
            border-radius: 50px;
            margin-top: 8px;
            border: 1px dashed rgba(15, 118, 110, 0.2);
        }
        .rating-stars {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #f59e0b; /* Gold standard star */
            font-weight: 700;
            font-size: 13.5px;
        }
        .metric-pill {
            font-size: 11.5px;
            font-weight: 600;
            background: var(--light-bg);
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body>

    <!-- Header bar -->
    <header class="navbar">
        <a href="marketplace.php" class="navbar-brand">
            <span>🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <a href="marketplace.php" style="color: var(--secondary); font-weight: 700;">Marketplace</a>
            <a href="my_orders.php" style="color: var(--text-muted); font-weight: 600;">My Orders</a>
            <a href="../market_prices.php" style="color: var(--text-muted); font-weight: 600;">Live Prices</a>
            <a href="../admin_complaints.php" style="color: #ef4444; font-weight: 700;">🛡️ Dispute Admin</a>
            
            <!-- Glowing Notification Bell -->
            <div class="notif-bell-container" id="notif-bell-btn">
                <span class="notif-bell-icon">🔔</span>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
                
                <!-- Notification Dropdown -->
                <div class="notif-dropdown" id="notif-dropdown-menu">
                    <div class="notif-dropdown-header">
                        <span>Notifications</span>
                        <?php if ($unread_count > 0): ?>
                            <button onclick="markAllNotificationsRead(event)">Mark all read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown-body">
                        <?php 
                        if ($notif_res && mysqli_num_rows($notif_res) > 0) {
                            while ($notif = mysqli_fetch_assoc($notif_res)) {
                                $unread_class = $notif['is_read'] == 0 ? 'unread' : '';
                                echo '<div class="notif-item ' . $unread_class . '">';
                                echo '<div class="notif-item-text">' . htmlspecialchars($notif['message']) . '</div>';
                                echo '<div class="notif-item-time">' . date("d M, h:i A", strtotime($notif['created_at'])) . '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">No new alerts.</div>';
                        }
                        ?>
                    </div>
                    <div class="notif-dropdown-footer">
                        <button onclick="window.location.reload();">Refresh Drawer</button>
                    </div>
                </div>
            </div>

            <div class="user-badge">
                <span>🛒</span> <?php echo htmlspecialchars($_SESSION['name']); ?> (Buyer)
            </div>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <!-- Marketplace Main Area -->
    <div class="grid-container animate-fade">
        
        <!-- Marketplace Banner -->
        <div class="marketplace-hero">
            <div style="max-width: 600px;">
                <h1 style="color: white; font-size: 36px; margin-bottom: 8px;">Direct Produce Marketplace</h1>
                <p style="color: rgba(255, 255, 255, 0.85); font-size: 16px;">
                    Trade with local farmers. Zero commissions, fresh inventory, and direct logistics.
                </p>
            </div>
            <span style="font-size: 64px; opacity: 0.85;">🛒</span>
        </div>

        <!-- Trending & Top-Selling Harvests Spotlight Section -->
        <?php if(mysqli_num_rows($result_trending) > 0) { ?>
            <div class="trending-section animate-slide">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h2 style="font-size: 22px; color: var(--dark); margin: 0; display: flex; align-items: center; gap: 8px;">
                            🔥 Trending Harvests & Top Sellers
                        </h2>
                        <p style="color: var(--text-muted); font-size: 13.5px; margin: 4px 0 0 0;">
                            Crops with the highest consumer satisfaction score, sales velocity, and distributor quality ratings.
                        </p>
                    </div>
                    <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--primary-hover); background: rgba(16, 185, 129, 0.1); padding: 6px 12px; border-radius: 50px; letter-spacing: 0.5px;">
                        Method: Top Traded × Star Rank
                    </span>
                </div>
                
                <div class="trending-grid">
                    <?php while($t_row = mysqli_fetch_assoc($result_trending)) { 
                        $t_crop_name = htmlspecialchars($t_row['crop_name']);
                        $t_price = (int)$t_row['price'];
                        $t_qty = (int)$t_row['quantity'];
                        $t_rating = number_format($t_row['rating_avg'], 1);
                        $t_reviews = (int)$t_row['review_count'];
                        $t_sales = (int)$t_row['sales_volume'];
                        $t_score = (int)$t_row['distributor_score'];
                        $t_badge = htmlspecialchars($t_row['distributor_badge']);
                        
                        $t_category = "Veg";
                        $t_emoji = "🥦";
                        if (stripos($t_crop_name, "wheat") !== false) {
                            $t_category = "Wheat";
                            $t_emoji = "🌾";
                        } else if (stripos($t_crop_name, "rice") !== false || stripos($t_crop_name, "paddy") !== false) {
                            $t_category = "Rice";
                            $t_emoji = "🍚";
                        } else if (stripos($t_crop_name, "potato") !== false) {
                            $t_category = "Potato";
                            $t_emoji = "🥔";
                        } else if (stripos($t_crop_name, "tomato") !== false) {
                            $t_category = "Tomato";
                            $t_emoji = "🍅";
                        } else if (stripos($t_crop_name, "mango") !== false || stripos($t_crop_name, "apple") !== false || stripos($t_crop_name, "fruit") !== false) {
                            $t_category = "Fruit";
                            $t_emoji = "🍎";
                        }
                    ?>
                        <div class="glass-card trending-card animate-fade" style="padding: 24px;">
                            <?php
                            $t_expiry_badge = "";
                            if (!empty($t_row['expiry_date'])) {
                                $diff_sec = strtotime($t_row['expiry_date']) - strtotime(date("Y-m-d"));
                                $diff_days = (int)round($diff_sec / 86400);
                                if ($diff_days <= 3) {
                                    $t_expiry_badge = '<span class="badge animate-pulse" style="background:var(--warning-light); color:#d97706; font-size:9.5px; font-weight:700; border:1px solid rgba(217,119,6,0.2); width:max-content; margin-top:4px;">⚠️ Expiring in '.$diff_days.'d</span>';
                                } else {
                                    $t_expiry_badge = '<span class="badge" style="background:rgba(59, 130, 246, 0.08); color:#2563eb; font-size:9.5px; font-weight:600; width:max-content; margin-top:4px;">📅 Fresh ('.$diff_days.'d left)</span>';
                                }
                            }
                            ?>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-left: 15px;">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-hover); font-size: 9.5px; font-weight: 700; width: max-content;">
                                        ⭐ <?php echo $t_category; ?> Class
                                    </span>
                                    <?php echo $t_expiry_badge; ?>
                                </div>
                                
                                <div class="rating-stars">
                                    ⭐ <?php echo $t_rating; ?> <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">(<?php echo $t_reviews; ?>)</span>
                                </div>
                            </div>
                            
                            <!-- Sustainability Badges Showcase (Phase 13.3) -->
                            <?php if (!empty($t_row['sustainability_badges'])) {
                                $t_badges = explode(",", $t_row['sustainability_badges']);
                                echo '<div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: -4px; margin-bottom: 10px; padding-left: 15px;">';
                                foreach ($t_badges as $badge) {
                                    $badge = trim($badge);
                                    if ($badge == 'organic') {
                                        echo '<span class="badge" style="background: rgba(16, 185, 129, 0.08); color: var(--primary-hover); font-size: 9px; font-weight: 700;">🌱 Organic</span>';
                                    } else if ($badge == 'water_efficient') {
                                        echo '<span class="badge" style="background: rgba(59, 130, 246, 0.08); color: #2563eb; font-size: 9px; font-weight: 700;">💧 Water Efficient</span>';
                                    } else if ($badge == 'eco_friendly') {
                                        echo '<span class="badge" style="background: rgba(245, 158, 11, 0.08); color: #d97706; font-size: 9px; font-weight: 700;">♻ Eco-Friendly</span>';
                                    }
                                }
                                echo '</div>';
                            } ?>

                            <?php if ($t_row['is_flagged'] == 1) { ?>
                                <div style="background: var(--danger-light); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.15); padding: 8px 12px; border-radius: var(--radius-sm); font-size: 11px; font-weight: 700; margin: 8px 0; margin-left: 15px; display: flex; align-items: center; gap: 6px; line-height: 1.3;">
                                    ⚠️ <strong>Price Auditor Alert:</strong> Suspect listing details under review.
                                </div>
                            <?php } ?>
                            
                            <h3 style="font-size: 19px; color: var(--dark); margin: 8px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 700; padding-left: 15px;">
                                <?php echo $t_emoji; ?> <?php echo $t_crop_name; ?>
                            </h3>
                            
                            <div style="display: flex; gap: 8px; margin-bottom: 14px; padding-left: 15px; align-items: center;">
                                <span class="metric-pill">📈 <?php echo $t_sales; ?> kg Sold</span>
                                <?php if ($t_qty > 20) { ?>
                                    <span class="metric-pill" style="color: var(--primary-hover); font-weight: 700;">🟢 Stock: <?php echo $t_qty; ?> kg</span>
                                <?php } else { ?>
                                    <span class="metric-pill animate-pulse" style="color: #d97706; font-weight: 700; background: rgba(245,158,11,0.05); border-color: rgba(245,158,11,0.15);">⚠️ Low Stock: Only <?php echo $t_qty; ?> kg!</span>
                                <?php } ?>
                            </div>

                            <!-- Premium Price Comparison Matrix -->
                            <div style="margin-left: 15px; margin-top: 14px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.03), rgba(15, 118, 110, 0.03)); border: 1px solid rgba(16, 185, 129, 0.08); padding: 12px; border-radius: var(--radius-sm);">
                                <span style="font-size: 11px; font-weight: 700; color: var(--secondary); display: block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">📊 Market Comparison Matrix:</span>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                                    <div style="display: flex; justify-content: space-between; padding-right: 6px; border-right: 1px solid var(--border);">
                                        <span style="color: var(--text-muted);">BigBasket:</span>
                                        <strong style="color: var(--danger);">₹<?php echo $t_row['ref_bigbasket_price'] ?: (int)($t_price * 1.35); ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding-left: 6px;">
                                        <span style="color: var(--text-muted);">Reliance:</span>
                                        <strong style="color: var(--danger);">₹<?php echo $t_row['ref_reliance_price'] ?: (int)($t_price * 1.30); ?></strong>
                                    </div>
                                </div>
                                <div style="margin-top: 6px; font-size: 11px; display: flex; justify-content: space-between; color: var(--text-muted); border-top: 1px dashed rgba(0,0,0,0.05); padding-top: 4px;">
                                    <span>🚜 Mandi wholesale: <strong>₹<?php echo $t_row['ref_mandi_price'] ?: (int)($t_price * 0.92); ?></strong></span>
                                    <span style="color: var(--primary-hover); font-weight: 700;">Saves up to 30%!</span>
                                </div>
                            </div>
                            
                            <!-- Distributor Verification Badge -->
                            <div style="border-top: 1px dashed var(--border); padding-top: 12px; margin-top: 12px; padding-left: 15px;">
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Grower Distributor:</div>
                                <div style="font-weight: 700; font-size: 14px; color: var(--dark); display: flex; align-items: center; justify-content: space-between; margin-top: 4px; margin-bottom: 6px;">
                                    <a href="../farmer/profile.php?id=<?php echo $t_row['farmer_id']; ?>" style="color: var(--secondary); text-decoration: underline;">
                                        👨‍🌾 <?php echo htmlspecialchars($t_row['farmer_name']); ?>
                                    </a>
                                    <span style="font-size: 11px; color: var(--primary-hover); font-weight: 800; background: var(--success-light); padding: 2px 6px; border-radius: 4px;">
                                        Quality Score: <?php echo $t_score; ?>%
                                    </span>
                                </div>
                                <div class="distributor-tag">
                                    🛡️ <?php echo $t_badge; ?>
                                </div>
                            </div>
                            
                            <!-- Price & Order Actions -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; background: var(--light-bg); padding: 10px 14px; border-radius: var(--radius-sm); margin-left: 15px;">
                                <div>
                                    <span style="font-size: 10px; color: var(--text-muted); display: block; line-height: 1;">Direct Rate:</span>
                                    <?php 
                                    $t_bargained_price = isset($t_row['bargained_price']) ? (int)$t_row['bargained_price'] : null;
                                    $t_display_price = $t_price;
                                    if ($t_bargained_price) {
                                        $t_display_price = $t_bargained_price;
                                    ?>
                                        <span style="font-size: 18px; font-weight: 800; color: var(--primary-hover);">
                                            <span style="text-decoration: line-through; color: var(--text-muted); font-size: 14px; font-weight: 500; margin-right: 4px;">₹<?php echo $t_price; ?></span>₹<?php echo $t_bargained_price; ?><span style="font-size: 11px; color: var(--text-muted);">/kg</span>
                                        </span>
                                        <div class="badge" style="background: var(--success-light); color: var(--primary-hover); font-size: 8.5px; padding: 2px 4px; border: 1px solid rgba(16,185,129,0.2); margin-top: 2px; width: max-content;">🎉 Bargained Deal</div>
                                    <?php } else { ?>
                                        <span style="font-size: 18px; font-weight: 800; color: var(--primary-hover);">₹<?php echo $t_price; ?><span style="font-size: 11px; color: var(--text-muted);">/kg</span></span>
                                    <?php } ?>
                                </div>
                                <button class="btn btn-primary trigger-order-modal" 
                                        data-crop-id="<?php echo $t_row['id']; ?>" 
                                        data-crop-name="<?php echo $t_crop_name; ?>" 
                                        data-crop-price="<?php echo $t_display_price; ?>" 
                                        data-crop-qty="<?php echo $t_qty; ?>"
                                        style="padding: 8px 14px; font-size: 12px;">
                                    <?php echo $t_bargained_price ? 'Order Deal 🤝' : 'Order Now 🤝'; ?>
                                </button>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            
            <!-- Reset pointer for the main list -->
            <?php mysqli_data_seek($result_trending, 0); ?>
        <?php } ?>

        <!-- Search Bar and Sorting Options -->
        <div class="search-container" style="display: flex; gap: 16px; margin-bottom: 28px; width: 100%;">
            <div class="search-bar" style="flex: 1;">
                <input type="text" id="marketplace-search" class="search-input" placeholder="🔍 Search fresh crops (e.g. Wheat, Basmati Rice, Potatoes)...">
            </div>
            <div class="sort-bar" style="min-width: 220px;">
                <select id="marketplace-sort" class="search-input" style="cursor: pointer; padding: 14px; background: white; border: 1px solid var(--border); border-radius: var(--radius-md); font-size: 15px; outline: none; width: 100%;">
                    <option value="default">⚡ Default Sorting</option>
                    <option value="sales">🔥 Top Selling (Trends)</option>
                    <option value="rating">⭐ Highest Rated</option>
                    <option value="price-low">💰 Price: Low to High</option>
                    <option value="price-high">📈 Price: High to Low</option>
                </select>
            </div>
        </div>

        <!-- Category Filter Pills -->
        <div class="category-pills">
            <button class="pill active" data-category="All">All Categories</button>
            <button class="pill" data-category="Wheat">🌾 Wheat</button>
            <button class="pill" data-category="Rice">🍚 Rice</button>
            <button class="pill" data-category="Potato">🥔 Potato</button>
            <button class="pill" data-category="Tomato">🍅 Tomato</button>
            <button class="pill" data-category="Fruit">🍎 Fruits</button>
            <button class="pill" data-category="Veg">🥦 Vegetables</button>
        </div>

        <!-- Marketplace Cards Grid -->
        <?php if(mysqli_num_rows($result) > 0) { ?>
            
            <div class="grid-4 marketplace-grid">
                <?php while($row = mysqli_fetch_assoc($result)) { 
                    $crop_name = htmlspecialchars($row['crop_name']);
                    $price = (int)$row['price'];
                    $qty = (int)$row['quantity'];
                    
                    // Categorize crops based on string content for JS filtering
                    $category = "Veg";
                    $emoji = "🥦";
                    
                    if (stripos($crop_name, "wheat") !== false) {
                        $category = "Wheat";
                        $emoji = "🌾";
                    } else if (stripos($crop_name, "rice") !== false || stripos($crop_name, "paddy") !== false) {
                        $category = "Rice";
                        $emoji = "🍚";
                    } else if (stripos($crop_name, "potato") !== false) {
                        $category = "Potato";
                        $emoji = "🥔";
                    } else if (stripos($crop_name, "tomato") !== false) {
                        $category = "Tomato";
                        $emoji = "🍅";
                    } else if (stripos($crop_name, "mango") !== false || stripos($crop_name, "apple") !== false || stripos($crop_name, "orange") !== false || stripos($crop_name, "fruit") !== false) {
                        $category = "Fruit";
                        $emoji = "🍎";
                    } else if (stripos($crop_name, "pulse") !== false || stripos($crop_name, "dal") !== false || stripos($crop_name, "bean") !== false) {
                        $category = "Wheat";
                        $emoji = "🌱";
                    }
                ?>
                    <?php
                    $expiry_badge = "";
                    if (!empty($row['expiry_date'])) {
                        $diff_sec = strtotime($row['expiry_date']) - strtotime(date("Y-m-d"));
                        $diff_days = (int)round($diff_sec / 86400);
                        if ($diff_days <= 3) {
                            $expiry_badge = '<span class="badge animate-pulse" style="background:var(--warning-light); color:#d97706; font-size:9.5px; font-weight:700; border:1px solid rgba(217,119,6,0.2); width:max-content; margin-top:4px;">⚠️ Expiring in '.$diff_days.'d</span>';
                        } else {
                            $expiry_badge = '<span class="badge" style="background:rgba(59, 130, 246, 0.08); color:#2563eb; font-size:9.5px; font-weight:600; width:max-content; margin-top:4px;">📅 Fresh ('.$diff_days.'d left)</span>';
                        }
                    }
                    
                    $bargained_price = isset($row['bargained_price']) ? (int)$row['bargained_price'] : null;
                    $display_price = $price;
                    if ($bargained_price) {
                        $display_price = $bargained_price;
                    }
                    ?>
                    <div class="glass-card animate-slide" 
                         data-category="<?php echo $category; ?>" 
                         data-sales="<?php echo $row['sales_volume']; ?>" 
                         data-rating="<?php echo $row['rating_avg']; ?>" 
                         data-price="<?php echo $display_price; ?>" 
                         data-index="<?php echo $row['id']; ?>">
                        <?php if (!empty($row['crop_image']) && file_exists("../uploads/crops/" . $row['crop_image'])) { ?>
                            <div class="crop-visual-badge" style="background-image: url('../uploads/crops/<?php echo htmlspecialchars($row['crop_image']); ?>'); background-size: cover; background-position: center; border: 1px solid rgba(16, 185, 129, 0.15);"></div>
                        <?php } else { ?>
                            <div class="crop-visual-badge">
                                <?php echo $emoji; ?>
                            </div>
                        <?php } ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <span class="badge" style="background: rgba(15, 118, 110, 0.08); color: var(--secondary); font-size: 10px; width: max-content;">
                                    <?php echo $category; ?>
                                </span>
                                <?php echo $expiry_badge; ?>
                            </div>
                            <?php if ($qty > 20) { ?>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.08); color: var(--primary-hover); font-size: 11px; font-weight: 700;">🟢 <?php echo $qty; ?> kg left</span>
                            <?php } else { ?>
                                <span class="badge animate-pulse" style="background: rgba(245, 158, 11, 0.08); color: #d97706; font-size: 11px; font-weight: 700; border: 1px solid rgba(245,158,11,0.15);">⚠️ Only <?php echo $qty; ?> kg left!</span>
                            <?php } ?>
                        </div>
                        
                        <!-- Sustainability Badges Renders (Phase 13.3) -->
                        <?php if (!empty($row['sustainability_badges'])) {
                            $badges = explode(",", $row['sustainability_badges']);
                            echo '<div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; margin-bottom: 8px;">';
                            foreach ($badges as $badge) {
                                $badge = trim($badge);
                                if ($badge == 'organic') {
                                    echo '<span class="badge" style="background: rgba(16, 185, 129, 0.05); color: var(--primary-hover); font-size: 9px; font-weight: 700;">🌱 Organic</span>';
                                } else if ($badge == 'water_efficient') {
                                    echo '<span class="badge" style="background: rgba(59, 130, 246, 0.05); color: #2563eb; font-size: 9px; font-weight: 700;">💧 Water Efficient</span>';
                                } else if ($badge == 'eco_friendly') {
                                    echo '<span class="badge" style="background: rgba(245, 158, 11, 0.05); color: #d97706; font-size: 9px; font-weight: 700;">♻ Eco-Friendly</span>';
                                }
                            }
                            echo '</div>';
                        } ?>

                        <!-- Automated Price Auditor Warning Banners (Phase 13.1) -->
                        <?php if ($row['is_flagged'] == 1) { ?>
                            <div style="background: var(--danger-light); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.15); padding: 8px 10px; border-radius: var(--radius-sm); font-size: 10.5px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 4px; line-height: 1.3;">
                                ⚠️ <strong>Price Auditor Warn:</strong> Suspicious supermarket rates variance under safety review.
                            </div>
                        <?php } ?>
                        
                        <h3 class="card-crop-name" style="font-size: 18px; margin-bottom: 8px; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo $crop_name; ?>
                        </h3>
                        
                        <!-- Ratings & Sales indicators -->
                        <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 12px; color: var(--text-muted); font-weight: 500;">
                            <span style="color: #f59e0b; font-weight: 700;">⭐ <?php echo number_format($row['rating_avg'], 1); ?> <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">(<?php echo $row['review_count']; ?>)</span></span>
                            <span>📈 <?php echo $row['sales_volume']; ?> kg sold</span>
                        </div>
                        
                        <!-- Connection info of the Farmer -->
                        <div style="background: var(--light-bg); padding: 10px 12px; border-radius: var(--radius-sm); margin-bottom: 10px; font-size: 13px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-muted); font-weight: 500;">👨‍🌾 Listed by:</span>
                                <span style="font-size: 10.5px; color: var(--primary-hover); font-weight: 800; background: var(--success-light); padding: 1px 4px; border-radius: 3px;">
                                    QS: <?php echo $row['distributor_score']; ?>%
                                </span>
                            </div>
                            <div style="font-weight: 700; margin: 2px 0 6px 0;">
                                <a href="../farmer/profile.php?id=<?php echo $row['farmer_id']; ?>" style="color: var(--secondary); text-decoration: underline;">
                                    <?php echo htmlspecialchars($row['farmer_name']); ?>
                                </a>
                            </div>
                            <div style="font-size: 10.5px; color: var(--secondary); font-weight: 700; background: rgba(15, 118, 110, 0.05); padding: 4px 8px; border-radius: 4px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">
                                🛡️ <?php echo htmlspecialchars($row['distributor_badge']); ?>
                            </div>
                        </div>

                        <!-- Premium Price Comparison Matrix -->
                        <div style="margin-bottom: 12px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.03), rgba(15, 118, 110, 0.03)); border: 1px solid rgba(16, 185, 129, 0.08); padding: 10px; border-radius: var(--radius-sm);">
                            <span style="font-size: 10.5px; font-weight: 700; color: var(--secondary); display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">📊 Market Comparison Matrix:</span>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 11px;">
                                <div style="display: flex; justify-content: space-between; padding-right: 4px; border-right: 1px solid var(--border);">
                                    <span style="color: var(--text-muted);">BigBasket:</span>
                                    <strong style="color: var(--danger);">₹<?php echo $row['ref_bigbasket_price'] ?: (int)($price * 1.35); ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-left: 4px;">
                                    <span style="color: var(--text-muted);">Reliance:</span>
                                    <strong style="color: var(--danger);">₹<?php echo $row['ref_reliance_price'] ?: (int)($price * 1.30); ?></strong>
                                </div>
                            </div>
                            <div style="margin-top: 4px; font-size: 10px; display: flex; justify-content: space-between; color: var(--text-muted); border-top: 1px dashed rgba(0,0,0,0.05); padding-top: 4px;">
                                <span>🚜 Mandi wholesale: <strong>₹<?php echo $row['ref_mandi_price'] ?: (int)($price * 0.92); ?></strong></span>
                                <span style="color: var(--primary-hover); font-weight: 700;">Save ~30%!</span>
                            </div>
                        </div>

                        <!-- Wholesale Suggestion Pill -->
                        <div style="font-size: 11.5px; font-weight: 600; color: var(--secondary); background: var(--primary-light); border: 1px solid rgba(16, 185, 129, 0.15); border-radius: var(--radius-sm); padding: 8px 10px; text-align: center; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                            <span>💡</span> Bulk deal? Negotiate direct volume parameters!
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; display: block; line-height: 1;">Price:</span>
                                    <?php if ($bargained_price) { ?>
                                        <span style="font-size: 18px; font-weight: 800; color: var(--primary-hover);">
                                            <span style="text-decoration: line-through; color: var(--text-muted); font-size: 14px; font-weight: 500; margin-right: 4px;">₹<?php echo $price; ?></span>₹<?php echo $bargained_price; ?><span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">/kg</span>
                                        </span>
                                        <span class="badge" style="background: var(--success-light); color: var(--primary-hover); font-size: 9px; padding: 2px 4px; border: 1px solid rgba(16,185,129,0.2); vertical-align: middle; display: inline-block;">🎉 Deal Active</span>
                                    <?php } else { ?>
                                        <span style="font-size: 18px; font-weight: 800; color: var(--primary-hover);">₹<?php echo $price; ?><span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">/kg</span></span>
                                    <?php } ?>
                                </div>
                                
                                <!-- Interactive Modal Trigger -->
                                <button class="btn btn-primary trigger-order-modal" 
                                        data-crop-id="<?php echo $row['id']; ?>" 
                                        data-crop-name="<?php echo $crop_name; ?>" 
                                        data-crop-price="<?php echo $display_price; ?>" 
                                        data-crop-qty="<?php echo $qty; ?>"
                                        style="padding: 8px 14px; font-size: 12px;">
                                    <?php echo $bargained_price ? 'Order Deal 🤝' : 'Order Now'; ?>
                                </button>
                            </div>
                            
                            <!-- Bargain Chat Link -->
                            <a href="../chat.php?farmer_id=<?php echo $row['farmer_id']; ?>&crop_id=<?php echo $row['id']; ?>" 
                               class="btn btn-secondary" style="padding: 8px; font-size: 12.5px; width: 100%; justify-content: center; margin-bottom: 4px;">
                                💬 Negotiate / Chat with Farmer
                            </a>

                            <!-- QR Scan to Order Button -->
                            <?php
                            $qr_order_url = "http://localhost/farm_portal/buyer/qr_order.php?crop_id={$row['id']}&qty=1&email=" . urlencode($row['farmer_email'] !== '' ? $_SESSION['name'] : '');
                            $qr_buyer_email = urlencode($_SESSION['name'] ?? '');
                            $qr_target_url  = "http://localhost/farm_portal/buyer/qr_order.php?crop_id={$row['id']}&qty=1";
                            $qr_img_url     = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&color=166534&bgcolor=f0fdf4&data=" . urlencode($qr_target_url);
                            ?>
                            <div style="border: 1px solid rgba(22,163,74,0.2); border-radius: var(--radius-sm); overflow: hidden; margin-bottom: 4px;">
                                <button onclick="toggleQR(this)"
                                        style="width:100%; padding:8px; font-size:12.5px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); color:#16a34a; border:none; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;">
                                    📱 Scan QR to Order Instantly
                                </button>
                                <div class="qr-panel" style="display:none; padding:14px; text-align:center; background:#f0fdf4;">
                                    <img src="<?php echo $qr_img_url; ?>"
                                         alt="QR Order"
                                         style="width:140px; height:140px; border-radius:8px; border:3px solid #dcfce7; display:block; margin: 0 auto 10px;">
                                    <div style="font-size:11px; color:#16a34a; font-weight:600; line-height:1.4;">
                                        Scan this QR with your phone<br>to place this order instantly
                                    </div>
                                    <a href="<?php echo $qr_target_url; ?>"
                                       style="display:inline-block; margin-top:10px; background:#16a34a; color:white; padding:8px 18px; border-radius:8px; font-size:12px; font-weight:700; text-decoration:none;">
                                        ✅ Order Now (Direct Link)
                                    </a>
                                </div>
                            </div>

                            <!-- Community Flagging Button -->
                            <button class="btn btn-secondary report-listing-btn" data-crop-id="<?php echo $row['id']; ?>" style="padding: 8px; font-size: 11.5px; width: 100%; justify-content: center; background: transparent; border: 1px solid rgba(239, 68, 68, 0.15); color: var(--danger);">
                                ⚠️ Report Spam / Fake Listing
                            </button>
                        </div>
                    </div>
                <?php } ?>
            </div>

        <?php } else { ?>
            
            <div class="empty-state animate-slide">
                <div class="empty-state-icon">🌾</div>
                <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 8px;">Marketplace is Empty</h3>
                <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;">
                    There are no crops listed for sale right now. Farmers are harvesting fresh crops daily, please check back in a little while!
                </p>
            </div>

        <?php } ?>

    </div>

    <!-- Stunning Frosted Order Pop-up Drawer (Wow Interactivity!) -->
    <div class="modal-overlay" id="buy-modal">
        <div class="modal-content">
            
            <div class="modal-header">
                <h3 id="modal-crop-name" style="font-size: 20px; color: var(--dark);">🌾 Place Order</h3>
                <button class="close-btn">&times;</button>
            </div>
            
            <form action="place_order.php" method="POST">
                
                <input type="hidden" id="modal-crop-id" name="crop_id">
                
                <div class="form-group" style="background: var(--light-bg); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                        <span style="color: var(--text-muted); font-weight: 500;">Maximum Available Stock:</span>
                        <span style="font-weight: 700; color: var(--dark);"><span id="modal-max-qty">0</span> kg</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="order-quantity">Quantity to Order (kg)</label>
                    <input class="form-control" type="number" id="order-quantity" name="quantity" min="1" value="1" required>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-label" for="payment-method">Payment Protocol</label>
                    <select class="form-control" id="payment-method" name="payment_method" style="background: white; border: 1px solid var(--border); font-weight: 600;" required>
                        <option value="COD">💵 Cash on Delivery (COD)</option>
                        <option value="UPI">⚡ Secure UPI Escrow (GPay/Paytm)</option>
                    </select>
                </div>
                
                <!-- Dynamic Cost Calculation (Handled instantly in app.js!) -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 18px; margin-top: 24px; margin-bottom: 24px;">
                    <div>
                        <span style="font-size: 13px; color: var(--text-muted); font-weight: 500; display: block;">Total Cost (Est.):</span>
                        <span style="font-size: 24px; font-weight: 800; color: var(--secondary);">₹<span id="total-price">0</span></span>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary close-btn" style="flex: 1; justify-content: center;">
                        Cancel
                    </button>
                    <button type="submit" name="place_order" class="btn btn-primary" style="flex: 1.5; justify-content: center;">
                        Confirm Purchase 🤝
                    </button>
                </div>
                
            </form>
            
        </div>
    </div>

    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>
    <script>
        // Toggle QR panel visibility on crop cards
        function toggleQR(btn) {
            const panel = btn.nextElementSibling;
            const isOpen = panel.style.display !== 'none';
            // Close all other open panels first
            document.querySelectorAll('.qr-panel').forEach(p => p.style.display = 'none');
            // Toggle this one
            panel.style.display = isOpen ? 'none' : 'block';
            btn.textContent = isOpen ? '📱 Scan QR to Order Instantly' : '✖ Close QR';
        }
    </script>
</body>
</html>