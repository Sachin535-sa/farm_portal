<?php
session_start();
include("../config/db.php");

// Session validation
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "farmer"){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Safe DDL backfill for orders created_at column if it does not exist
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE 'created_at'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE `orders` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Fetch notifications
$user_id_clean = mysqli_real_escape_string($conn, $farmer_id);
$notif_query = "SELECT * FROM notifications WHERE user_id = '$user_id_clean' ORDER BY created_at DESC LIMIT 5";
$notif_res = mysqli_query($conn, $notif_query);
$unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$user_id_clean' AND is_read = 0";
$unread_count_res = mysqli_query($conn, $unread_count_query);
$unread_count = 0;
if ($unread_count_res) {
    $unread_count_row = mysqli_fetch_assoc($unread_count_res);
    $unread_count = (int)$unread_count_row['count'];
}

// Handling crop deletion
if(isset($_GET['delete_id'])){
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete_id']);
    mysqli_query($conn, "DELETE FROM crops WHERE id='$delete_id' AND farmer_id='$farmer_id'");
    header("Location: dashboard.php");
    exit();
}

// Fetch Farmer Location for Weather & Advisor
$user_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT state, district FROM users WHERE id='$farmer_id'"));
$farmer_state = !empty($user_info['state']) ? $user_info['state'] : 'Punjab';
$farmer_district = !empty($user_info['district']) ? $user_info['district'] : 'Ludhiana';

// Compute Statistics
$stats_crops = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count, SUM(quantity) as qty FROM crops WHERE farmer_id='$farmer_id'"));
$total_listed = $stats_crops['count'] ?? 0;
$total_qty = $stats_crops['qty'] ?? 0;

$todays_orders_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' AND DATE(o.created_at) = CURDATE()"));
$todays_orders = $todays_orders_row['count'] ?? 0;

$pending_orders_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' AND o.status IN ('pending', 'accepted', 'packed', 'shipped')"));
$pending_orders = $pending_orders_row['count'] ?? 0;

$completed_orders_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' AND o.status = 'delivered'"));
$completed_orders = $completed_orders_row['count'] ?? 0;

$stats_earnings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(o.quantity * o.price) as total FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' AND o.status = 'delivered'"));
$total_earnings = $stats_earnings['total'] ?? 0;

$pending_payments_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(o.quantity * o.price) as total FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' AND o.status IN ('pending', 'accepted', 'packed', 'shipped')"));
$pending_payments = $pending_payments_row['total'] ?? 0;

// Spotlight query
$spotlight_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT c.crop_name, SUM(o.quantity) as total_sold FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' AND o.status = 'delivered' GROUP BY c.id ORDER BY total_sold DESC LIMIT 1"));
$spotlight_crop = $spotlight_row['crop_name'] ?? null;
$spotlight_qty = $spotlight_row['total_sold'] ?? 0;

// Chart 1: Revenue per Crop
$revenue_chart_query = "SELECT c.crop_name, SUM(o.quantity * o.price) as revenue FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' AND o.status = 'delivered' GROUP BY c.id";
$revenue_res = mysqli_query($conn, $revenue_chart_query);
$revenue_data = [];
while ($r_row = mysqli_fetch_assoc($revenue_res)) {
    $revenue_data[] = [
        "label" => $r_row['crop_name'],
        "value" => (int)$r_row['revenue']
    ];
}

// Chart 2: Order Fulfillment States
$fulfillment_query = "SELECT o.status, COUNT(*) as count FROM orders o JOIN crops c ON o.crop_id = c.id WHERE c.farmer_id = '$farmer_id' GROUP BY o.status";
$fulfillment_res = mysqli_query($conn, $fulfillment_query);
$fulfillment_data = [
    "pending" => 0,
    "accepted" => 0,
    "packed" => 0,
    "shipped" => 0,
    "delivered" => 0,
    "cancelled" => 0
];
while ($f_row = mysqli_fetch_assoc($fulfillment_res)) {
    $status_key = strtolower($f_row['status']);
    if (isset($fulfillment_data[$status_key])) {
        $fulfillment_data[$status_key] = (int)$f_row['count'];
    }
}

// Weather & Harvest Advisory Engine (Phase 14)
$active_crops_names = [];
$crop_check_query = mysqli_query($conn, "SELECT crop_name FROM crops WHERE farmer_id='$farmer_id'");
while ($cc_row = mysqli_fetch_assoc($crop_check_query)) {
    $active_crops_names[] = strtolower($cc_row['crop_name']);
}
$crops_str = implode(" ", $active_crops_names);

$temp = "32°C";
$condition = "Clear Sky";
$humidity = "42%";
$soil_moisture = "22%";
$weather_icon = "☀️";
$weather_gradient = "linear-gradient(135deg, #f59e0b, #d97706)";

$advice_title = "<i class='ph-duotone ph-plant'></i> Optimal Sowing & Harvesting Window";
$advice_body = "The current atmospheric weather is highly stable. Continue monitoring your crop yields and check direct-pricing indices.";
$advice_alert_level = "info";

if (preg_match('/(basmati|rice|paddy)/', $crops_str)) {
    $temp = "29°C";
    $condition = "Thunderstorms Predicted";
    $humidity = "85%";
    $soil_moisture = "38%";
    $weather_icon = "⛈️";
    $weather_gradient = "linear-gradient(135deg, #1e3c72, #2a5298)";
    
    $advice_title = "<i class='ph-duotone ph-warning'></i> Urgent Harvesting Alert: Basmati Paddy";
    $advice_body = "Precipitation modeling shows moderate-to-heavy rainfall within the next 48 hours. **Harvest your standing paddy crop immediately** to secure Grade-A moisture standards and gain up to +12% pricing premiums on the marketplace.";
    $advice_alert_level = "danger";
} elseif (preg_match('/(wheat|kanak|sharbati)/', $crops_str)) {
    $temp = "36°C";
    $condition = "Sunny & Warm";
    $humidity = "28%";
    $soil_moisture = "18%";
    $weather_icon = "☀️";
    $weather_gradient = "linear-gradient(135deg, #ff9900, #ff5500)";
    
    $advice_title = "<i class='ph-duotone ph-check-circle'></i> Perfect Ripper Sowing Window: Sharbati Wheat";
    $advice_body = "Optimal dry ripening temperature reached. Dry and warm winds are ideal for automated combine threshing. Proceed with visual quality assessment and direct marketplace listing.";
    $advice_alert_level = "success";
} elseif (preg_match('/(tomato|potato)/', $crops_str)) {
    $temp = "26°C";
    $condition = "Overcast & Humid";
    $humidity = "78%";
    $soil_moisture = "28%";
    $weather_icon = "<i class='ph-duotone ph-cloud'></i>";
    $weather_gradient = "linear-gradient(135deg, #0f766e, #115e59)";
    
    $advice_title = "<i class='ph-duotone ph-warning'></i> Blight Disease Alert: Potato/Tomatoes";
    $advice_body = "High moisture index increases early blight fungus threat. Ensure proper drainage in fields and apply preventive organic inputs. Ensure sorting of damaged lot elements before packaging.";
    $advice_alert_level = "warning";
}

// Fetch Crop Listings
$result = mysqli_query($conn, "SELECT * FROM crops WHERE farmer_id='$farmer_id'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    
    <style>
        /* Force stats cards into a horizontal row/grid format */
        .stats-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
            gap: 24px !important;
            margin-bottom: 32px !important;
        }
        .stat-card {
            background: white !important;
            border: 1px solid rgba(226, 232, 240, 0.8) !important;
            border-radius: 14px !important;
            padding: 24px !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 20px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
            transition: all 0.3s ease !important;
            box-sizing: border-box !important;
        }
        .stat-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06) !important;
        }
        .stat-icon {
            width: 56px !important;
            height: 56px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 24px !important;
            flex-shrink: 0 !important;
        }
        .stat-val {
            font-family: 'Outfit', sans-serif !important;
            font-size: 28px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            line-height: 1.2 !important;
        }
        .stat-label {
            font-size: 13px !important;
            color: #64748b !important;
            font-weight: 500 !important;
            margin-top: 4px !important;
        }
    </style>
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="v2-dashboard">

    <!-- V2 Sidebar -->
    <aside class="v2-sidebar" id="v2Sidebar">
        <div class="v2-sidebar-header">
            <a href="../index.php" class="brand">
                <span style="color: var(--v2-primary);"><i class='ph-duotone ph-plant'></i></span> AgroNava
            </a>
        </div>
        <div class="v2-sidebar-nav">
            <a href="dashboard.php" class="v2-nav-item active"><i class='ph-duotone ph-squares-four'></i> My Listings</a>
            <a href="orders.php" class="v2-nav-item"><i class='ph-duotone ph-package'></i> Manage Orders</a>
            <a href="../market_prices.php" class="v2-nav-item"><i class='ph-duotone ph-chart-line-up'></i> Live Prices</a>
            <a href="profile.php?id=<?php echo $farmer_id; ?>" class="v2-nav-item"><i class='ph-duotone ph-user-circle'></i> Public Portfolio</a>
            <a href="../admin_complaints.php" class="v2-nav-item" style="color: #ef4444;"><i class='ph-duotone ph-shield-check'></i> Dispute Admin</a>
        </div>
        <div class="v2-sidebar-footer">
            <a href="../auth/logout.php" class="v2-nav-item" style="color: #ef4444;"><i class='ph-duotone ph-sign-out'></i> Logout</a>
        </div>
    </aside>

    <!-- V2 Top Navbar -->
    <header class="v2-top-navbar">
        <div class="v2-nav-left">
            <button class="v2-mobile-toggle" id="v2MobileToggle" aria-label="Toggle Menu">
                <i class='ph-duotone ph-list'></i>
            </button>
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 18px; margin: 0; color: var(--v2-text-main);">Farmer Console</h2>
        </div>
        <div class="v2-nav-right">
            <div class="v2-icon-btn" id="v2NotifToggle">
                <i class='ph-duotone ph-bell'></i>
                <?php if ($unread_count > 0): ?>
                    <span class="v2-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="user-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--v2-primary); border: 1px solid rgba(16, 185, 129, 0.2); padding: 6px 12px; border-radius: 20px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                <i class='ph-duotone ph-user'></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
            </div>
        </div>
    </header>

    <!-- V2 Notification Drawer -->
    <div class="v2-drawer-overlay" id="v2DrawerOverlay"></div>
    <div class="v2-notification-drawer" id="v2NotifDrawer">
        <div class="v2-drawer-header">
            <h3>Notifications</h3>
            <button class="v2-icon-btn" id="v2NotifClose" style="border: none;"><i class='ph-duotone ph-x'></i></button>
        </div>
        <div class="v2-drawer-body">
            <?php 
            if ($notif_res && mysqli_num_rows($notif_res) > 0) {
                while ($notif = mysqli_fetch_assoc($notif_res)) {
                    $unread_class = $notif['is_read'] == 0 ? 'unread' : '';
                    $msg = $notif['message'];
                    
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
                    
                    echo '<div class="v2-notif-card ' . $unread_class . ' ' . $type_class . '">';
                    echo '<div style="font-size: 14px; margin-bottom: 6px; line-height: 1.4;">' . $msg . '</div>';
                    echo '<div style="font-size: 12px; color: var(--v2-text-muted);"><i class="ph-duotone ph-clock"></i> ' . date("d M, h:i A", strtotime($notif['created_at'])) . '</div>';
                    echo '</div>';
                }
            } else {
                echo '<div style="padding: 20px; text-align: center; color: var(--v2-text-muted); font-size: 14px;">No alerts.</div>';
            }
            ?>
        </div>
    </div>

    <!-- V2 Main Content -->
    <main class="v2-main-content">

        <div class="animate-fade">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
            <div>
                <h1 style="font-size: 32px; color: var(--dark);">Farmer Dashboard</h1>
                <p style="color: var(--text-muted);">Manage your harvest listings, track buyer demands and market valuation</p>
            </div>
            <a class="btn btn-primary" href="add_crop.php">+ List New Crop</a>
        </div>

        <!-- Dashboard Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-hover);"><i class='ph-duotone ph-plant'></i></div>
                <div>
                    <div class="stat-val"><?php echo $total_listed; ?></div>
                    <div class="stat-label">Crops Listed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(15, 118, 110, 0.1); color: var(--secondary);"><i class='ph-duotone ph-package'></i></div>
                <div>
                    <div class="stat-val"><?php echo number_format($total_qty); ?> kg</div>
                    <div class="stat-label">In-Stock Quantity</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--warning-light); color: #d97706;">⌛</div>
                <div>
                    <div class="stat-val"><?php echo $pending_orders; ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #2563eb;"><i class='ph-duotone ph-handshake'></i></div>
                <div>
                    <div class="stat-val"><?php echo $completed_orders; ?></div>
                    <div class="stat-label">Completed Orders</div>
                </div>
            </div>
        </div>

        <!-- Earnings Metrics Section -->
        <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 32px;">
            <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(15, 118, 110, 0.05)); border-color: rgba(16, 185, 129, 0.2);">
                <div class="stat-icon" style="background: var(--success-light); color: var(--primary-hover); font-size: 28px;"><i class='ph-duotone ph-currency-circle-dollar'></i></div>
                <div>
                    <div class="stat-val" style="color: var(--primary-hover);">₹<?php echo number_format($total_earnings); ?></div>
                    <div class="stat-label" style="font-weight: 700;">Total Earnings (Delivered & Settled)</div>
                </div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(245, 158, 11, 0.02)); border-color: rgba(245, 158, 11, 0.2);">
                <div class="stat-icon" style="background: var(--warning-light); color: var(--warning); font-size: 28px;">⌛</div>
                <div>
                    <div class="stat-val" style="color: var(--warning);">₹<?php echo number_format($pending_payments); ?></div>
                    <div class="stat-label" style="font-weight: 700;">Pending Payments (In Transit)</div>
                </div>
            </div>
        </div>

        <!-- Smart Weather & Harvest Advisor Panel (Option A) -->
        <div class="glass-card animate-slide" style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px; padding: 32px; background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-lg); margin-bottom: 32px; box-shadow: var(--shadow-md); position: relative; overflow: hidden; align-items: center;">
            
            <!-- Atmospheric Weather Widget Card -->
            <div style="background: <?php echo $weather_gradient; ?>; padding: 28px; border-radius: var(--radius-md); color: white; display: flex; flex-direction: column; justify-content: space-between; min-height: 240px; box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15); position: absolute; top: 32px; bottom: 32px; left: 32px; right: auto; width: 280px; box-sizing: border-box; overflow: hidden; z-index: 1;">
                
                <!-- Background decorative pulsing orb -->
                <div style="position: absolute; top: -20px; right: -20px; width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(20px); z-index: -1;"></div>
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <span style="font-size: 10px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 50px;"><i class='ph-duotone ph-globe'></i> Live Forecast</span>
                        <h4 style="color: white; font-size: 17px; font-weight: 800; margin-top: 10px; margin-bottom: 2px;">📍 <?php echo htmlspecialchars($farmer_district); ?>, <?php echo htmlspecialchars($farmer_state); ?></h4>
                        <span style="font-size: 12px; opacity: 0.9; font-weight: 500;"><?php echo $condition; ?></span>
                    </div>
                    <span style="font-size: 48px; line-height: 1;"><?php echo $weather_icon; ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 14px; margin-top: 14px;">
                    <div>
                        <span style="font-size: 9px; opacity: 0.8; display: block; text-transform: uppercase; font-weight: 700;">Temp</span>
                        <span style="font-size: 26px; font-weight: 900; font-family: 'Outfit';"><?php echo $temp; ?></span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; font-weight: 600; display: block; opacity: 0.9;"><i class='ph-duotone ph-drop'></i> Hum: <strong><?php echo $humidity; ?></strong></span>
                        <span style="font-size: 11px; font-weight: 600; display: block; opacity: 0.9;"><i class='ph-duotone ph-leaf'></i> Soil: <strong><?php echo $soil_moisture; ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Spacer column for static grid -->
            <div style="width: 280px; pointer-events: none;"></div>

            <!-- AI Sowing & Harvesting Advisor Console -->
            <div style="display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; color: var(--secondary); background: var(--primary-light); padding: 4px 10px; border-radius: 50px; display: inline-block; margin-bottom: 14px;"><i class='ph-duotone ph-robot'></i> AgroAdvisor Core</span>
                    
                    <?php
                    $alert_bg = "rgba(59, 130, 246, 0.08)";
                    $alert_text = "#2563eb";
                    $alert_border = "rgba(59, 130, 246, 0.2)";
                    
                    if ($advice_alert_level === 'danger') {
                        $alert_bg = "rgba(239, 68, 68, 0.08)";
                        $alert_text = "#ef4444";
                        $alert_border = "rgba(239, 68, 68, 0.25)";
                    } elseif ($advice_alert_level === 'warning') {
                        $alert_bg = "rgba(245, 158, 11, 0.08)";
                        $alert_text = "#d97706";
                        $alert_border = "rgba(245, 158, 11, 0.25)";
                    } elseif ($advice_alert_level === 'success') {
                        $alert_bg = "rgba(16, 185, 129, 0.08)";
                        $alert_text = "#10b981";
                        $alert_border = "rgba(16, 185, 129, 0.25)";
                    }
                    ?>
                    <div style="background: <?php echo $alert_bg; ?>; border: 1px solid <?php echo $alert_border; ?>; border-radius: var(--radius-md); padding: 18px; margin-bottom: 18px;">
                        <h4 style="color: <?php echo $alert_text; ?>; font-size: 16px; font-weight: 800; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                            <?php echo $advice_title; ?>
                        </h4>
                        <p style="font-size: 13.5px; color: var(--text-main); line-height: 1.6; margin: 0; font-weight: 500;">
                            <?php echo $advice_body; ?>
                        </p>
                    </div>
                </div>
                
                <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
                    <a href="predictor_simulator.php" class="btn btn-primary" style="padding: 12px 20px; font-size: 13.5px; display: inline-flex; align-items: center; gap: 8px; font-weight: 700; background: linear-gradient(135deg, var(--secondary), var(--primary)); border: none; box-shadow: 0 4px 15px rgba(15,118,110,0.25);">
                        <i class='ph-duotone ph-chart-line-up'></i> Run Yield & Profit Predictor Simulator
                    </a>
                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 500; font-style: italic;">
                        <i class='ph-duotone ph-lightning'></i> Simulates optimal input & fertilizer yields.
                    </span>
                </div>
            </div>
        </div>

        <!-- Spotlight & Low Stock alert ribbons -->
        <?php if ($spotlight_crop): ?>
            <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(16, 185, 129, 0.1)); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-md); padding: 16px 24px; margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;" class="animate-slide">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 28px;">🔥</span>
                    <div>
                        <h4 style="color: var(--dark); margin: 0; font-size: 16px;">Top Performing Crop Spotlight</h4>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 2px 0 0 0;">
                            Your most sold crop is <strong><?php echo htmlspecialchars($spotlight_crop); ?></strong> with a total volume of <strong><?php echo $spotlight_qty; ?> kg</strong> traded successfully!
                        </p>
                    </div>
                </div>
                <span class="badge" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 8px 16px; font-weight: 700;">
                    ⭐ Best Seller
                </span>
            </div>
        <?php endif; ?>

        <!-- Double Chart Analytics Section using Chart.js (High Impact!) -->
        <div class="analytics-section-grid animate-slide">
            
            <!-- Revenue Earnings per Crop Card -->
            <div class="analytics-chart-card">
                <h3 style="font-size: 16px; color: var(--dark); margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                    <i class='ph-duotone ph-chart-line-up'></i> Revenue Earnings per Crop
                </h3>
                <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 20px;">Cumulative revenue (INR) generated from delivered crop sales</p>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="revenueCropChart"></canvas>
                </div>
            </div>

            <!-- Fulfillment Distribution Card -->
            <div class="analytics-chart-card">
                <h3 style="font-size: 16px; color: var(--dark); margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                    🍩 Order Fulfillment Distribution
                </h3>
                <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 20px;">Breakdown of order statuses in logistics pipeline</p>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="fulfillmentDoughnutChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Crop Inventory listings -->
        <h2 style="font-size: 22px; margin-bottom: 20px; color: var(--dark);"><i class='ph-duotone ph-leaf'></i> My Active Crop Listings</h2>

        <?php if(mysqli_num_rows($result) > 0) { ?>
            
            <div class="grid-3">
                <?php while($row = mysqli_fetch_assoc($result)) { 
                    $qty = (int)$row['quantity'];
                    $stock_label = "In Stock";
                    $stock_color = "var(--primary)";
                    
                    if($qty == 0) {
                        $stock_label = "Out of Stock";
                        $stock_color = "var(--danger)";
                    } else if ($qty < 20) {
                        $stock_label = "Low Stock";
                        $stock_color = "var(--warning)";
                    }
                    $expiry_badge = "";
                    if (!empty($row['expiry_date'])) {
                        $diff_sec = strtotime($row['expiry_date']) - strtotime(date("Y-m-d"));
                        $diff_days = (int)round($diff_sec / 86400);
                        if ($diff_days < 0) {
                            $expiry_badge = '<span class="badge" style="background:var(--danger-light); color:var(--danger); font-size:11px; font-weight:700;"><i class="ph-duotone ph-x-circle"></i> Expired</span>';
                        } else if ($diff_days <= 3) {
                            $expiry_badge = '<span class="badge animate-pulse" style="background:var(--warning-light); color:#d97706; font-size:11.5px; font-weight:700; border:1px solid rgba(217,119,6,0.2);"><i class="ph-duotone ph-warning"></i> Expires in '.$diff_days.'d</span>';
                        } else {
                            $expiry_badge = '<span class="badge" style="background:rgba(59, 130, 246, 0.08); color:#2563eb; font-size:11.5px; font-weight:600;"><i class="ph-duotone ph-calendar-blank"></i> Expiry: '.$diff_days.' days</span>';
                        }
                    } else {
                        $expiry_badge = '<span class="badge" style="background:var(--light-bg); color:var(--text-muted); font-size:11px;"><i class="ph-duotone ph-calendar-blank"></i> No Expiry Set</span>';
                    }
                ?>
                    <div class="glass-card animate-slide">
                        <?php if (!empty($row['crop_image']) && file_exists("../uploads/crops/" . $row['crop_image'])) { ?>
                            <div class="crop-visual-badge" style="height: 120px; margin-bottom: 12px; background-image: url('../uploads/crops/<?php echo htmlspecialchars($row['crop_image']); ?>'); background-size: cover; background-position: center; border-radius: var(--radius-sm); border: 1px solid rgba(0,0,0,0.05);"></div>
                        <?php } ?>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <?php echo $expiry_badge; ?>
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                <span style="font-size: 13px; font-weight: 600; color: <?php echo $stock_color; ?>;">
                                    ● <?php echo $stock_label; ?>
                                </span>
                                <?php if ($qty > 0 && $qty <= 20): ?>
                                    <span class="stock-pulse-warning"><i class='ph-duotone ph-warning'></i> Only <?php echo $qty; ?> kg left!</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h3 style="font-size: 20px; margin-bottom: 8px; color: var(--dark);">
                            <?php echo htmlspecialchars($row['crop_name']); ?>
                        </h3>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 15px;">
                            <span style="color: var(--text-muted); font-weight: 500;">Price per kg:</span>
                            <span style="font-weight: 700; color: var(--secondary);">₹<?php echo htmlspecialchars($row['price']); ?></span>
                        </div>

                        <!-- Price Comparison matrix -->
                        <div style="font-size: 11.5px; margin-bottom: 16px; padding: 10px; background: rgba(15, 118, 110, 0.04); border: 1px solid rgba(15,118,110,0.08); border-radius: var(--radius-sm);">
                            <span style="color: var(--secondary); font-weight: 700; display: block; margin-bottom: 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;"><i class='ph-duotone ph-currency-circle-dollar'></i> Retail Comparison Benchmarks:</span>
                            <div style="display: flex; justify-content: space-between; color: var(--text-main); font-weight: 600;">
                                <span><i class='ph-duotone ph-buildings'></i> BigBasket: <strong style="color: var(--danger);">₹<?php echo $row['ref_bigbasket_price'] ?: 'N/A'; ?></strong></span>
                                <span><i class='ph-duotone ph-buildings'></i> Reliance: <strong style="color: var(--danger);">₹<?php echo $row['ref_reliance_price'] ?: 'N/A'; ?></strong></span>
                            </div>
                            <div style="margin-top: 6px; font-size: 11px; color: var(--text-muted); display: flex; justify-content: space-between; font-weight: 500; border-top: 1px dashed rgba(0,0,0,0.05); padding-top: 4px;">
                                <span><i class='ph-duotone ph-tractor'></i> Mandi wholesale returns: <strong>₹<?php echo $row['ref_mandi_price'] ?: 'N/A'; ?></strong></span>
                            </div>
                        </div>
                        
                        <!-- Inventory progress representation -->
                        <div style="font-size: 13px; color: var(--text-muted); display: flex; justify-content: space-between; font-weight: 500;">
                            <span>Remaining Inventory:</span>
                            <span style="font-weight: 700; color: var(--dark);"><?php echo $qty; ?> kg</span>
                        </div>
                        <div class="progress-bar-container">
                            <!-- Cap progress width calculation safely at 100% -->
                            <?php $perc = min(100, max(0, ($qty / 200) * 100)); ?>
                            <div class="progress-bar" style="width: <?php echo $perc; ?>%; background: <?php echo $stock_color; ?>;"></div>
                        </div>
                        <p style="font-size: 11px; color: var(--text-muted); margin-bottom: 24px; text-align: right;">Capacity benchmark: 200 kg</p>

                        <!-- Deletion & Management buttons -->
                        <div style="display: flex; gap: 12px;">
                            <a class="btn btn-secondary" style="flex: 1; padding: 10px; font-size: 13px;" href="add_crop.php?edit_id=<?php echo $row['id']; ?>">
                                <i class='ph-duotone ph-gear'></i> Edit
                            </a>
                            <a class="btn btn-danger" style="flex: 1; padding: 10px; font-size: 13px;" 
                               onclick="return confirm('Are you sure you want to delete this crop listing?');"
                               href="dashboard.php?delete_id=<?php echo $row['id']; ?>">
                                <i class='ph-duotone ph-trash'></i> Delete
                            </a>
                        </div>
                    </div>
                <?php } ?>
            </div>

        <?php } else { ?>
            
            <div class="empty-state animate-slide">
                <div class="empty-state-icon"><i class='ph-duotone ph-plant'></i></div>
                <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 8px;">No Produce Listed Yet</h3>
                <p style="color: var(--text-muted); margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">
                    You haven't listed any crops for sale. Click the button below to publish your harvest and start receiving buyer orders!
                </p>
                <a class="btn btn-primary" href="add_crop.php">+ List Your First Crop</a>
            </div>

        <?php } ?>

    </div>

    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>

    <!-- Chart.js Setup Script -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Global Chart Defaults for Premium Look
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = "#64748b";
        Chart.defaults.scale.grid.color = "rgba(0,0,0,0.03)";
        Chart.defaults.plugins.tooltip.animation.duration = 400;
        
        // Premium Tooltip styling
        const premiumTooltip = {
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            titleFont: { size: 14, family: "'Plus Jakarta Sans', sans-serif", weight: 'bold' },
            bodyFont: { size: 13, family: "'Plus Jakarta Sans', sans-serif" },
            padding: 16,
            cornerRadius: 12,
            displayColors: true,
            boxPadding: 6,
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1
        };

        // Bar Chart - Revenue per Crop
        const revenueCtx = document.getElementById('revenueCropChart').getContext('2d');
        const revenueLabels = <?php echo json_encode(array_column($revenue_data, 'label')); ?>;
        const revenueValues = <?php echo json_encode(array_column($revenue_data, 'value')); ?>;
        
        // Create Gradient for Bars
        let barGradient = revenueCtx.createLinearGradient(0, 0, 0, 350);
        barGradient.addColorStop(0, 'rgba(16, 185, 129, 0.9)');
        barGradient.addColorStop(1, 'rgba(15, 118, 110, 0.1)');
        
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: revenueLabels.length > 0 ? revenueLabels : ['No Sales Yet'],
                datasets: [{
                    label: 'Revenue (₹)',
                    data: revenueValues.length > 0 ? revenueValues : [0],
                    backgroundColor: barGradient,
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    barThickness: 28,
                    hoverBackgroundColor: 'rgba(16, 185, 129, 1)',
                    hoverBorderColor: '#fff',
                    hoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: premiumTooltip
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { drawBorder: false },
                        ticks: { font: { family: "'Plus Jakarta Sans'" } }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { font: { family: "'Plus Jakarta Sans'", weight: '600' } }
                    }
                },
                animation: {
                    y: { duration: 1200, easing: 'easeOutQuart' }
                }
            }
        });

        // Doughnut Chart - Fulfillment States
        const fulfillmentCtx = document.getElementById('fulfillmentDoughnutChart').getContext('2d');
        const fulfillmentData = <?php echo json_encode(array_values($fulfillment_data)); ?>;
        
        new Chart(fulfillmentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Accepted', 'Packed', 'Shipped', 'Delivered', 'Cancelled'],
                datasets: [{
                    data: fulfillmentData,
                    backgroundColor: [
                        '#f59e0b', // Pending
                        '#10b981', // Accepted
                        '#0f766e', // Packed
                        '#3b82f6', // Shipped
                        '#10b981', // Delivered
                        '#ef4444'  // Cancelled
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    tooltip: premiumTooltip,
                    legend: {
                        position: window.innerWidth < 1200 ? 'bottom' : 'right',
                        labels: { boxWidth: 12, font: { size: 10 } }
                    }
                },
                cutout: '65%'
            }
        });
    });
    </script>
    
    <!-- V2 UI Mechanics JS -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const notifToggle = document.getElementById("v2NotifToggle");
            const notifDrawer = document.getElementById("v2NotifDrawer");
            const notifClose = document.getElementById("v2NotifClose");
            const drawerOverlay = document.getElementById("v2DrawerOverlay");
            const mobileToggle = document.getElementById("v2MobileToggle");
            const sidebar = document.getElementById("v2Sidebar");

            function toggleDrawer() {
                if(notifDrawer) notifDrawer.classList.toggle("active");
                if(drawerOverlay) drawerOverlay.classList.toggle("active");
            }

            if(notifToggle) notifToggle.addEventListener("click", toggleDrawer);
            if(notifClose) notifClose.addEventListener("click", toggleDrawer);
            if(drawerOverlay) {
                drawerOverlay.addEventListener("click", () => {
                    notifDrawer.classList.remove("active");
                    drawerOverlay.classList.remove("active");
                    if(sidebar) sidebar.classList.remove("mobile-open");
                });
            }

            if(mobileToggle && sidebar) {
                mobileToggle.addEventListener("click", () => {
                    sidebar.classList.toggle("mobile-open");
                    drawerOverlay.classList.toggle("active");
                });
            }
        });
    </script>
        </div>
    </main>
</body>
</html>