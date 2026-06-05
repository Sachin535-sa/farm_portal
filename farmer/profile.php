<?php
session_start();
include("../config/db.php");

// Session check (buyers or other farmers can view profiles)
if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

if(!isset($_GET['id'])){
    header("Location: ../index.php");
    exit();
}

$profile_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch Farmer Details
$sql_farmer = "SELECT name, email, distributor_badge, distributor_score FROM users WHERE id='$profile_id' AND role='farmer'";
$res_farmer = mysqli_query($conn, $sql_farmer);

if(mysqli_num_rows($res_farmer) == 0){
    // Not a valid farmer profile
    header("Location: ../index.php");
    exit();
}

$farmer = mysqli_fetch_assoc($res_farmer);

// Fetch only this farmer's crop listings
$sql_crops = "SELECT * FROM crops WHERE farmer_id='$profile_id' AND quantity > 0";
$result_crops = mysqli_query($conn, $sql_crops);

// Fetch Farmer's Crop Statistics dynamically based on ratings and sales volumes
$sql_stats = "SELECT AVG(rating_avg) as avg_rating, SUM(review_count) as total_reviews, SUM(sales_volume) as total_sales FROM crops WHERE farmer_id='$profile_id'";
$res_stats = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($res_stats);

$avg_rating = ($stats['avg_rating'] !== null) ? number_format($stats['avg_rating'], 1) : "4.5";
$total_reviews = isset($stats['total_reviews']) ? (int)$stats['total_reviews'] : 0;
$total_sales = isset($stats['total_sales']) ? (int)$stats['total_sales'] : 0;

// DYNAMIC REPUTATION SCORE CALCULATIONS (Phase 13.3)
// 1. Fetch Order stats for delivery rate
$sql_delivered = "SELECT COUNT(*) as count FROM orders JOIN crops ON orders.crop_id = crops.id WHERE crops.farmer_id = '$profile_id' AND orders.status = 'delivered'";
$res_delivered = mysqli_query($conn, $sql_delivered);
$delivered_count = mysqli_fetch_assoc($res_delivered)['count'];

$sql_non_cancelled = "SELECT COUNT(*) as count FROM orders JOIN crops ON orders.crop_id = crops.id WHERE crops.farmer_id = '$profile_id' AND orders.status != 'cancelled'";
$res_non_cancelled = mysqli_query($conn, $sql_non_cancelled);
$non_cancelled_count = mysqli_fetch_assoc($res_non_cancelled)['count'];

$delivery_success_rate = 1.0;
if ($non_cancelled_count > 0) {
    $delivery_success_rate = $delivered_count / $non_cancelled_count;
}

// 2. Avg rating and reviews calculations
$avg_rating_val = ($stats['avg_rating'] !== null) ? (float)$stats['avg_rating'] : 4.5;
$rating_percentage = ($avg_rating_val / 5.0) * 100;
$review_vol = (int)$total_reviews;
$review_weight = ($review_vol / ($review_vol + 10)) * 100;

// 3. Formula: Success (40%) + Rating (40%) + Reviews weight (20%)
$dynamic_reputation = ($delivery_success_rate * 40) + ($rating_percentage * 0.40) + ($review_weight * 0.20);
$dynamic_reputation = (int)round($dynamic_reputation);

if ($dynamic_reputation < 0) $dynamic_reputation = 0;
if ($dynamic_reputation > 100) $dynamic_reputation = 100;

// Fallback to 85 if no historical data exists
if ($non_cancelled_count == 0 && $review_vol == 0) {
    $dynamic_reputation = 85;
}

// Determine active badge based on score
$distributor_badge = "Standard Distributor";
if ($dynamic_reputation >= 95) {
    $distributor_badge = "🥇 Certified Top-Tier Quality Distributor";
} else if ($dynamic_reputation >= 90) {
    $distributor_badge = "🌟 Highly Rated Eco-Grower";
} else if ($dynamic_reputation >= 75) {
    $distributor_badge = "Verified Premium Partner";
}

// Check for flagged crop listings (fraud monitoring)
$sql_flagged_crops = "SELECT COUNT(*) as count FROM crops WHERE farmer_id = '$profile_id' AND is_flagged = 1";
$res_flagged_crops = mysqli_query($conn, $sql_flagged_crops);
$flagged_crops_count = mysqli_fetch_assoc($res_flagged_crops)['count'];

// Parse achievements from all listed crops
$accomplishments = [];
$res_badges = mysqli_query($conn, "SELECT DISTINCT sustainability_badges FROM crops WHERE farmer_id='$profile_id'");
if ($res_badges) {
    while($badge_row = mysqli_fetch_assoc($res_badges)) {
        if (!empty($badge_row['sustainability_badges'])) {
            $b_list = explode(",", $badge_row['sustainability_badges']);
            foreach($b_list as $b) {
                $b = trim($b);
                if(!empty($b) && !in_array($b, $accomplishments)) {
                    $accomplishments[] = $b;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($farmer['name']); ?> Portfolio | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .profile-header {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 32px;
            margin-bottom: 40px;
        }
        
        .farm-map-card {
            background: linear-gradient(135deg, #10b981, #0f766e);
            border-radius: var(--radius-md);
            height: 100%;
            min-height: 240px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        /* Minimalist topographic design layout representing map overlay */
        .map-contour-1 {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            border: 2px dashed rgba(255,255,255,0.15);
            animation: pulse 12s linear infinite;
        }
        
        .map-contour-2 {
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.25);
            animation: pulse 8s linear infinite reverse;
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
        
        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(0.95); opacity: 0.5; }
        }
        
        @media (max-width: 1024px) {
            .profile-header {
                grid-template-columns: 1.2fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .profile-header {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Header bar dynamically adjusting to session -->
    <header class="navbar">
        <a href="../index.php" class="navbar-brand">
            <span>🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <?php if($_SESSION['role'] == 'farmer') { ?>
                <a href="dashboard.php" style="color: var(--secondary); font-weight: 700;">My Listings</a>
                <div class="user-badge">👨‍🌾 <?php echo htmlspecialchars($_SESSION['name']); ?></div>
            <?php } else { ?>
                <a href="../buyer/marketplace.php" style="color: var(--secondary); font-weight: 700;">Marketplace</a>
                <div class="user-badge">🛒 <?php echo htmlspecialchars($_SESSION['name']); ?></div>
            <?php } ?>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <div class="grid-container animate-fade">
        
        <!-- Navigation bar -->
        <div style="margin-bottom: 24px;">
            <?php if($_SESSION['role'] == 'buyer') { ?>
                <a href="../buyer/marketplace.php" style="color: var(--text-muted); font-weight: 600;">← Back to Marketplace</a>
            <?php } else { ?>
                <a href="dashboard.php" style="color: var(--text-muted); font-weight: 600;">← Back to Dashboard</a>
            <?php } ?>
        </div>

        <!-- Farmer Profile Header Grid -->
        <div class="profile-header">
            
            <div class="glass-card animate-slide" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                        <span style="font-size: 48px;">👨‍🌾</span>
                        <div>
                            <h1 style="font-size: 28px; color: var(--dark); line-height: 1.1;">
                                <?php echo htmlspecialchars($farmer['name']); ?>
                            </h1>
                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-hover); font-size: 11px;">
                                    ✓ Verified Active Grower
                                </span>
                                <?php if (!empty($distributor_badge)) { ?>
                                    <span class="badge" style="background: rgba(15, 118, 110, 0.1); color: var(--secondary); font-size: 11px;">
                                        🛡️ <?php echo htmlspecialchars($distributor_badge); ?>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    
                    <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 24px; font-style: italic;">
                        "Specializes in chemical-free, sustainable farming practices. Proud grower of native, high-yielding grains and fresh, sun-ripened seasonal vegetables."
                    </p>

                    <!-- Sustainability Accomplishments Showcase -->
                    <?php if (!empty($accomplishments)) { ?>
                        <div style="margin-top: 16px; border-top: 1px dashed var(--border); padding-top: 12px; margin-bottom: 16px;">
                            <span style="display: block; font-size: 11px; color: var(--secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 6px; letter-spacing: 0.5px;">🌱 Sustainable accomplishments</span>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php foreach ($accomplishments as $badge) { 
                                    if ($badge == 'organic') {
                                        echo '<span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-hover); font-size: 11px; font-weight: 600; padding: 4px 8px;">🌱 Organic Certified</span>';
                                    } else if ($badge == 'water_efficient') {
                                        echo '<span class="badge" style="background: rgba(59, 130, 246, 0.1); color: #2563eb; font-size: 11px; font-weight: 600; padding: 4px 8px;">💧 Water Efficient</span>';
                                    } else if ($badge == 'eco_friendly') {
                                        echo '<span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #d97706; font-size: 11px; font-weight: 600; padding: 4px 8px;">♻ Eco-Friendly Methodologies</span>';
                                    }
                                } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                
                <!-- Farmer Statistics -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; border-top: 1px solid var(--border); padding-top: 20px;">
                    <div>
                        <span style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Rating Score</span>
                        <span style="font-size: 17px; font-weight: 800; color: var(--warning);"><?php echo $avg_rating; ?> ★ <span style="font-size: 11px; font-weight: 500; color: var(--text-muted);">(<?php echo $total_reviews; ?>)</span></span>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Trade Volume</span>
                        <span style="font-size: 17px; font-weight: 800; color: var(--primary-hover);"><?php echo number_format($total_sales); ?> kg</span>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Trust Index</span>
                        <span style="font-size: 17px; font-weight: 800; color: var(--secondary);"><?php echo $dynamic_reputation; ?>%</span>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Experience</span>
                        <span style="font-size: 17px; font-weight: 800; color: var(--dark);">8+ Years</span>
                    </div>
                </div>
            </div>

            <!-- Dynamic circular trust score meter (Phase 13.3) -->
            <div class="glass-card animate-slide" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 24px;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 12.5px; color: var(--secondary); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Distributor Trust Meter</h3>
                
                <div style="position: relative; width: 140px; height: 140px; margin-bottom: 12px;">
                    <svg style="transform: rotate(-90deg); width: 140px; height: 140px;">
                        <circle cx="70" cy="70" r="58" stroke="rgba(15, 118, 110, 0.08)" stroke-width="10" fill="transparent" />
                        <circle cx="70" cy="70" r="58" stroke="url(#trustGradient)" stroke-width="10" fill="transparent"
                                stroke-dasharray="364.42" stroke-dashoffset="<?php echo 364.42 - (364.42 * $dynamic_reputation / 100); ?>"
                                stroke-linecap="round" style="transition: stroke-dashoffset 1s ease-out;" />
                        <defs>
                            <linearGradient id="trustGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#10b981" />
                                <stop offset="100%" stop-color="#0f766e" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span style="font-family: 'Outfit', sans-serif; font-size: 32px; font-weight: 800; color: var(--dark); line-height: 1;"><?php echo $dynamic_reputation; ?>%</span>
                        <span style="font-size: 9px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px;">Reputation</span>
                    </div>
                </div>
                
                <h4 style="font-size: 13px; font-weight: 700; color: var(--dark); margin-bottom: 4px;"><?php echo htmlspecialchars($distributor_badge); ?></h4>
                <p style="font-size: 10.5px; color: var(--text-muted); line-height: 1.4; margin: 0;">
                    Real-time metrics tracking delivery rates, product reviews, and listing safety ratings.
                </p>

                <?php if ($flagged_crops_count >= 2) { ?>
                    <div style="background: var(--danger-light); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); padding: 8px 12px; border-radius: var(--radius-sm); font-size: 10.5px; font-weight: 700; margin-top: 14px; display: flex; align-items: center; gap: 6px; text-align: left; line-height: 1.3;">
                        <span>⚠️</span>
                        <span><strong>SEVERE WARNING:</strong> Multiple listings flagged for suspicious price audits.</span>
                    </div>
                <?php } ?>
            </div>

            <!-- CSS simulated map locator -->
            <div class="farm-map-card animate-fade">
                <div class="map-contour-1"></div>
                <div class="map-contour-2"></div>
                <div style="text-align: center; z-index: 5;">
                    <span style="font-size: 32px; display: block; animation: float 3s ease-in-out infinite;">📍</span>
                    <h4 style="color: white; font-size: 16px; margin-top: 6px;">Karnal, Haryana</h4>
                    <p style="font-size: 12px; color: rgba(255,255,255,0.85); font-weight: 500;">Simulated Farm Coordinates</p>
                </div>
            </div>
            
        </div>

        <!-- Dynamic catalog by this specific farmer -->
        <h2 style="font-size: 22px; color: var(--dark); margin-bottom: 20px;">🌾 Active Harvest Offerings by this Farmer</h2>

        <?php if(mysqli_num_rows($result_crops) > 0) { ?>
            
            <div class="grid-4">
                <?php while($row = mysqli_fetch_assoc($result_crops)) { 
                    $crop_name = htmlspecialchars($row['crop_name']);
                    $price = (int)$row['price'];
                    $qty = (int)$row['quantity'];
                    
                    // Deduce emoji
                    $emoji = "🥦";
                    if (stripos($crop_name, "wheat") !== false) $emoji = "🌾";
                    else if (stripos($crop_name, "rice") !== false || stripos($crop_name, "paddy") !== false) $emoji = "🍚";
                    else if (stripos($crop_name, "potato") !== false) $emoji = "🥔";
                    else if (stripos($crop_name, "tomato") !== false) $emoji = "🍅";
                    else if (stripos($crop_name, "fruit") !== false || stripos($crop_name, "apple") !== false) $emoji = "🍎";
                ?>
                    <div class="glass-card animate-slide">
                        <?php if (!empty($row['crop_image']) && file_exists("../uploads/crops/" . $row['crop_image'])) { ?>
                            <div class="crop-visual-badge" style="background-image: url('../uploads/crops/<?php echo htmlspecialchars($row['crop_image']); ?>'); background-size: cover; background-position: center; border: 1px solid rgba(16, 185, 129, 0.15);"></div>
                        <?php } else { ?>
                            <div class="crop-visual-badge">
                                <?php echo $emoji; ?>
                            </div>
                        <?php } ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                            <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-hover); font-size: 10px;">
                                Active Harvest
                            </span>
                            <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                                Stock: <?php echo $qty; ?> kg
                            </span>
                        </div>
                        
                        <h3 style="font-size: 18px; margin-bottom: 8px; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo $crop_name; ?>
                        </h3>
                        
                        <!-- Ratings & Sales indicators -->
                        <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 14px; color: var(--text-muted); font-weight: 500;">
                            <span style="color: #f59e0b; font-weight: 700;">⭐ <?php echo number_format($row['rating_avg'], 1); ?> <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">(<?php echo $row['review_count']; ?>)</span></span>
                            <span>📈 <?php echo $row['sales_volume']; ?> kg sold</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; display: block; line-height: 1;">Price:</span>
                                <span style="font-size: 20px; font-weight: 800; color: var(--primary-hover);">₹<?php echo $price; ?><span style="font-size: 12px; font-weight: 600; color: var(--text-muted);">/kg</span></span>
                            </div>
                            
                            <?php if($_SESSION['role'] == 'buyer') { ?>
                                <button class="btn btn-primary trigger-order-modal" 
                                        data-crop-id="<?php echo $row['id']; ?>" 
                                        data-crop-name="<?php echo $crop_name; ?>" 
                                        data-crop-price="<?php echo $price; ?>" 
                                        data-crop-qty="<?php echo $qty; ?>"
                                        style="padding: 10px 16px; font-size: 13px;">
                                    Order
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

        <?php } else { ?>
            
            <div class="empty-state animate-slide">
                <div class="empty-state-icon">📭</div>
                <h3 style="font-size: 18px; color: var(--dark); margin-bottom: 8px;">No Produce Available</h3>
                <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;">
                    This farmer currently has no active stock listed on the marketplace. Please check back later.
                </p>
            </div>

        <?php } ?>

    </div>

    <!-- Stunning Frosted Order Pop-up Drawer (Dynamic Buying form) -->
    <div class="modal-overlay" id="buy-modal">
        <div class="modal-content">
            
            <div class="modal-header">
                <h3 id="modal-crop-name" style="font-size: 20px; color: var(--dark);">🌾 Place Order</h3>
                <button class="close-btn">&times;</button>
            </div>
            
            <form action="../buyer/place_order.php" method="POST">
                
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
</body>
</html>
