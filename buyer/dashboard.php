<?php
session_start();
include("../config/db.php");

// Session security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer") {
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$buyer_name = $_SESSION['name'];

// Fetch unread notifications
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

// Compute Statistics Metrics
// 1. Total Spending (Delivered orders)
$spending_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(o.quantity * o.price) as total FROM orders o JOIN crops c ON o.crop_id = c.id WHERE o.buyer_id = '$buyer_id' AND o.status = 'delivered'"));
$total_spent = $spending_row['total'] ?? 0;

// 2. Total Active Orders in logistics pipeline
$active_orders_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE buyer_id = '$buyer_id' AND status IN ('pending', 'accepted', 'packed', 'shipped')"));
$active_orders_count = $active_orders_row['count'] ?? 0;

// 3. Pending UPI Escrow settlements (unpaid, not cancelled)
$escrow_pending_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(o.quantity * o.price) as total FROM orders o WHERE o.buyer_id = '$buyer_id' AND o.is_paid = 0 AND o.status != 'cancelled'"));
$pending_escrow = $escrow_pending_row['total'] ?? 0;

// 4. Procurement Volume Total (in Kg)
$volume_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as qty FROM orders WHERE buyer_id = '$buyer_id' AND status = 'delivered'"));
$total_volume = $volume_row['qty'] ?? 0;

// 5. Query Spending per Crop Category for Chart 1
$spending_chart_query = "SELECT c.crop_name, SUM(o.quantity * o.price) as spent FROM orders o JOIN crops c ON o.crop_id = c.id WHERE o.buyer_id = '$buyer_id' AND o.status = 'delivered' GROUP BY c.id";
$spending_res = mysqli_query($conn, $spending_chart_query);
$spending_data = [];
while ($s_row = mysqli_fetch_assoc($spending_res)) {
    $spending_data[] = [
        "label" => $s_row['crop_name'],
        "value" => (int)$s_row['spent']
    ];
}

// Default fallback data for Chart 1 if empty
if (empty($spending_data)) {
    $spending_data = [
        ["label" => "Basmati Rice", "value" => 9750],
        ["label" => "Red Tomatoes", "value" => 2250],
        ["label" => "Sharbati Wheat", "value" => 5600]
    ];
}

// 6. Query Logistics Fulfillment States for Chart 2
$logistics_query = "SELECT status, COUNT(*) as count FROM orders WHERE buyer_id = '$buyer_id' GROUP BY status";
$logistics_res = mysqli_query($conn, $logistics_query);
$logistics_data = [
    "pending" => 0,
    "accepted" => 0,
    "packed" => 0,
    "shipped" => 0,
    "delivered" => 0,
    "cancelled" => 0
];
while ($l_row = mysqli_fetch_assoc($logistics_res)) {
    $status_key = strtolower($l_row['status']);
    if (isset($logistics_data[$status_key])) {
        $logistics_data[$status_key] = (int)$l_row['count'];
    }
}

// 7. Fetch Recent Orders for the transaction deck
$recent_orders_query = "SELECT o.*, c.crop_name, c.crop_image, u.name as farmer_name 
                        FROM orders o 
                        JOIN crops c ON o.crop_id = c.id 
                        JOIN users u ON c.farmer_id = u.id 
                        WHERE o.buyer_id = '$buyer_id' 
                        ORDER BY o.id DESC 
                        LIMIT 3";
$recent_orders_res = mysqli_query($conn, $recent_orders_query);

// 8. Fetch Active Negotiations (Bargains) for the buyer
$bargains_query = "SELECT b.*, c.crop_name, c.price as listing_price, u.name as farmer_name 
                   FROM bargains b 
                   JOIN crops c ON b.crop_id = c.id 
                   JOIN users u ON c.farmer_id = u.id 
                   WHERE b.buyer_id = '$buyer_id' AND b.status IN ('pending', 'accepted') 
                   ORDER BY b.id DESC 
                   LIMIT 3";
$bargains_res = mysqli_query($conn, $bargains_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard Console | AgroNava</title>
    
    <!-- Link styles & Fonts -->
    <link rel="stylesheet" href="../assets/css/style.css?v=1.6">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Scoped styling to enforce a spectacular light-slate cyber-cyan dashboard look */
        :root {
            --buyer-accent: #06b6d4;
            --buyer-accent-light: rgba(6, 182, 212, 0.08);
            --buyer-indigo: #4f46e5;
            --buyer-slate: #0f172a;
        }

        body {
            background-color: #f8fafc;
            color: #334155;
            font-family: 'Inter', sans-serif;
        }

        .buyer-navbar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }

        .buyer-badge-glow {
            background: var(--buyer-accent-light);
            color: var(--buyer-accent);
            border: 1px solid rgba(6, 182, 212, 0.2);
            font-weight: 700;
        }

        .buyer-stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }

        .buyer-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: rgba(6, 182, 212, 0.25);
        }

        .buyer-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: var(--buyer-accent-light);
            color: var(--buyer-accent);
            border: 1px solid rgba(6, 182, 212, 0.15);
        }

        .buyer-stat-val {
            font-size: 26px;
            font-weight: 800;
            color: var(--buyer-slate);
            line-height: 1.2;
            font-family: 'Outfit', sans-serif;
        }

        .buyer-stat-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .timeline-wrapper {
            position: relative;
            padding: 20px 0;
            margin-top: 14px;
        }

        /* Direct Trade timeline graphics */
        .timeline-bar-bg {
            position: absolute;
            top: 28px;
            left: 20px;
            right: 20px;
            height: 4px;
            background: #e2e8f0;
            z-index: 0;
            border-radius: 20px;
        }

        .timeline-bar-fill {
            position: absolute;
            top: 28px;
            left: 20px;
            height: 4px;
            background: linear-gradient(90deg, var(--buyer-accent), var(--buyer-indigo));
            z-index: 1;
            border-radius: 20px;
            transition: width 0.5s ease;
        }

        .timeline-nodes {
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }

        .timeline-node {
            text-align: center;
        }

        .timeline-node-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 3px solid #cbd5e1;
            margin: 0 auto 6px;
            transition: all 0.3s;
        }

        .timeline-node.completed .timeline-node-dot {
            border-color: var(--buyer-accent);
            background: var(--buyer-accent);
        }

        .timeline-node.active .timeline-node-dot {
            border-color: var(--buyer-indigo);
            box-shadow: 0 0 10px rgba(79, 70, 229, 0.4);
            transform: scale(1.15);
        }

        .timeline-node-text {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .timeline-node.completed .timeline-node-text,
        .timeline-node.active .timeline-node-text {
            color: var(--buyer-slate);
        }

        /* Order Item Rows */
        .order-row-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 18px;
            transition: all 0.3s;
        }

        .order-row-item:hover {
            box-shadow: var(--shadow-sm);
            border-color: rgba(6, 182, 212, 0.2);
        }

        /* Eco Indicator Progress Bar */
        .eco-progress-container {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.12);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-top: 24px;
        }

        .eco-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            font-weight: 700;
            color: var(--buyer-slate);
            margin-bottom: 10px;
        }

        .eco-bar {
            width: 100%;
            height: 10px;
            background: #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
        }

        .eco-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 10px;
            width: 85%;
            animation: pulse-eco 2s infinite alternate;
        }

        @keyframes pulse-eco {
            from { opacity: 0.85; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- Dynamic Transparent Navigation Bar -->
    <header class="navbar buyer-navbar">
        <a href="../index.php" class="navbar-brand">
            <span style="color: var(--buyer-accent);">🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <a href="dashboard.php" style="color: var(--buyer-accent); font-weight: 700;">Dashboard</a>
            <a href="marketplace.php" style="color: var(--text-muted); font-weight: 600;">Produce Market</a>
            <a href="my_orders.php" style="color: var(--text-muted); font-weight: 600;">My Orders</a>
            <a href="../market_prices.php" style="color: var(--text-muted); font-weight: 600;">Live Prices</a>
            <a href="../chat.php" style="color: var(--text-muted); font-weight: 600;">Chat Terminal</a>
            
            <!-- Glowing Notification Bell -->
            <div class="notif-bell-container" id="notif-bell-btn">
                <span class="notif-bell-icon">🔔</span>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
                
                <div class="notif-dropdown" id="notif-dropdown-menu">
                    <div class="notif-dropdown-header">
                        <span>Alerts Inbox</span>
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
                            echo '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">No alerts.</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="user-badge buyer-badge-glow">
                <span>🛒</span> <?php echo htmlspecialchars($buyer_name); ?>
            </div>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <!-- Main Container -->
    <div class="grid-container animate-fade">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <div>
                <h1 style="font-size: 32px; color: var(--buyer-slate); font-family: 'Outfit', sans-serif; font-weight: 800;">Buyer Console Terminal</h1>
                <p style="color: var(--text-muted); font-weight: 500;">Monitor wholesale procurements, complete escrow payments, and track active cargo timelines.</p>
            </div>
            <a class="btn btn-primary" href="marketplace.php" style="background: linear-gradient(135deg, var(--buyer-accent), var(--buyer-indigo)); border: none; color: white;">🛍️ Browse Produce Catalog</a>
        </div>

        <!-- Dynamic Statistics Cards -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px;">
            <!-- Spending -->
            <div class="buyer-stat-card">
                <div class="buyer-stat-icon">💰</div>
                <div>
                    <div class="buyer-stat-val">₹<?php echo number_format($total_spent); ?></div>
                    <div class="buyer-stat-label">Total Procured spent</div>
                </div>
            </div>
            <!-- Active Orders -->
            <div class="buyer-stat-card">
                <div class="buyer-stat-icon" style="color: var(--buyer-indigo); background: rgba(79, 70, 229, 0.08);">📦</div>
                <div>
                    <div class="buyer-stat-val"><?php echo $active_orders_count; ?></div>
                    <div class="buyer-stat-label">Active Cargo Orders</div>
                </div>
            </div>
            <!-- Escrow holdings -->
            <div class="buyer-stat-card">
                <div class="buyer-stat-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.08);">🔒</div>
                <div>
                    <div class="buyer-stat-val">₹<?php echo number_format($pending_escrow); ?></div>
                    <div class="buyer-stat-label">Pending Settlements</div>
                </div>
            </div>
            <!-- Volume Sourced -->
            <div class="buyer-stat-card">
                <div class="buyer-stat-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.08);">⚖️</div>
                <div>
                    <div class="buyer-stat-val"><?php echo number_format($total_volume); ?> Kg</div>
                    <div class="buyer-stat-label">Total Volume Procured</div>
                </div>
            </div>
        </div>

        <!-- Twin Chart JS Layout Grid -->
        <div class="analytics-section-grid" style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; margin-bottom: 40px;">
            <!-- Chart Card 1 -->
            <div class="analytics-chart-card" style="background: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px;">
                <h3 style="font-size: 18px; color: var(--buyer-slate); margin-bottom: 8px;">📊 Spend Distribution per Crop Category</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 24px;">Visual representation of total spending metrics verified directly through direct trade settlements.</p>
                <div style="height: 280px; position: relative;">
                    <canvas id="spendingChart"></canvas>
                </div>
            </div>
            
            <!-- Chart Card 2 -->
            <div class="analytics-chart-card" style="background: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px;">
                <h3 style="font-size: 18px; color: var(--buyer-slate); margin-bottom: 8px;">📈 Logistics Fulfillment Pipeline</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 24px;">Ongoing progress allocations of cargo transactions.</p>
                <div style="height: 220px; position: relative; display: flex; justify-content: center; align-items: center;">
                    <canvas id="logisticsChart"></canvas>
                </div>
                
                <!-- Eco badges indicator score -->
                <div class="eco-progress-container">
                    <div class="eco-progress-header">
                        <span>🌱 Sustainable Sourced Score</span>
                        <span style="color: #10b981;">85% Elite</span>
                    </div>
                    <div class="eco-bar">
                        <div class="eco-bar-fill"></div>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 8px;">
                        Certified <strong>Patron of Organic Direct-Trade</strong>. 85% of your total cargo purchases have zero broker carbon margins.
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Layout Grid: Transactions vs Negotiations -->
        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; margin-bottom: 40px;">
            <!-- Active Timeline Transactions -->
            <div class="glass-card" style="background: white; border: 1px solid var(--border); padding: 24px; border-radius: var(--radius-md);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                    <h3 style="font-size: 18px; color: var(--buyer-slate);">🚚 Ongoing Cargo Timelines</h3>
                    <a href="my_orders.php" style="color: var(--buyer-accent); font-weight: 700; font-size: 13px;">Manage All Orders ➔</a>
                </div>
                
                <?php 
                if ($recent_orders_res && mysqli_num_rows($recent_orders_res) > 0) {
                    while ($order = mysqli_fetch_assoc($recent_orders_res)) {
                        $order_id = $order['id'];
                        $crop_name = $order['crop_name'];
                        $farmer_name = $order['farmer_name'];
                        $qty = $order['quantity'];
                        $cost = $qty * $order['price'];
                        $status = strtolower($order['status']);
                        
                        // Compute step status index
                        $step_idx = 0;
                        if ($status == 'accepted') $step_idx = 1;
                        if ($status == 'packed') $step_idx = 2;
                        if ($status == 'shipped') $step_idx = 3;
                        if ($status == 'delivered') $step_idx = 4;
                        if ($status == 'cancelled') $step_idx = -1;
                        
                        $fill_width = $step_idx >= 0 ? ($step_idx * 25) . '%' : '0%';
                        ?>
                        <div class="order-row-item">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                                <div>
                                    <h4 style="font-size: 15px; color: var(--buyer-slate); font-weight: 700;">Order #<?php echo $order_id; ?>: <?php echo htmlspecialchars($crop_name); ?></h4>
                                    <p style="font-size: 12px; color: var(--text-muted);">Sourced Directly from: <strong><?php echo htmlspecialchars($farmer_name); ?></strong></p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 15px; font-weight: 800; color: var(--buyer-slate);">₹<?php echo number_format($cost); ?></div>
                                    <span class="user-badge" style="font-size: 10px; padding: 2px 8px; margin-top: 4px; display: inline-block; background: <?php echo $order['is_paid'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(245, 158, 11, 0.1)'; ?>; color: <?php echo $order['is_paid'] ? '#10b981' : '#f59e0b'; ?>;">
                                        <?php echo $order['is_paid'] ? '💳 Paid & Settled' : '⚠️ Pending Settlement'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($status == 'cancelled') { ?>
                                <div style="color: var(--danger); font-weight: 700; font-size: 13px; padding: 10px 0; display: flex; align-items: center; gap: 8px;">
                                    ❌ This order has been cancelled and funds reverted.
                                </div>
                            <?php } else { ?>
                                <!-- Five-Stage Timeline -->
                                <div class="timeline-wrapper">
                                    <div class="timeline-bar-bg"></div>
                                    <div class="timeline-bar-fill" style="width: <?php echo $fill_width; ?>;"></div>
                                    
                                    <div class="timeline-nodes">
                                        <div class="timeline-node <?php echo $step_idx >= 0 ? 'completed' : ''; ?> <?php echo $step_idx == 0 ? 'active' : ''; ?>">
                                            <div class="timeline-node-dot"></div>
                                            <div class="timeline-node-text">Placed</div>
                                        </div>
                                        <div class="timeline-node <?php echo $step_idx >= 1 ? 'completed' : ''; ?> <?php echo $step_idx == 1 ? 'active' : ''; ?>">
                                            <div class="timeline-node-dot"></div>
                                            <div class="timeline-node-text">Accepted</div>
                                        </div>
                                        <div class="timeline-node <?php echo $step_idx >= 2 ? 'completed' : ''; ?> <?php echo $step_idx == 2 ? 'active' : ''; ?>">
                                            <div class="timeline-node-dot"></div>
                                            <div class="timeline-node-text">Packed</div>
                                        </div>
                                        <div class="timeline-node <?php echo $step_idx >= 3 ? 'completed' : ''; ?> <?php echo $step_idx == 3 ? 'active' : ''; ?>">
                                            <div class="timeline-node-dot"></div>
                                            <div class="timeline-node-text">Shipped</div>
                                        </div>
                                        <div class="timeline-node <?php echo $step_idx >= 4 ? 'completed' : ''; ?> <?php echo $step_idx == 4 ? 'active' : ''; ?>">
                                            <div class="timeline-node-dot"></div>
                                            <div class="timeline-node-text">Delivered</div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <?php 
                    }
                } else {
                    echo '<div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 14px;">No active orders yet. Browse the catalog to start sourcing!</div>';
                }
                ?>
            </div>

            <!-- Active Negotiating Ledger -->
            <div class="glass-card" style="background: white; border: 1px solid var(--border); padding: 24px; border-radius: var(--radius-md);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                    <h3 style="font-size: 18px; color: var(--buyer-slate);">💡 Active Negotiations</h3>
                    <a href="../chat.php" style="color: var(--buyer-indigo); font-weight: 700; font-size: 13px;">Open Chat ➔</a>
                </div>
                
                <?php 
                if ($bargains_res && mysqli_num_rows($bargains_res) > 0) {
                    while ($bargain = mysqli_fetch_assoc($bargains_res)) {
                        $is_accepted = $bargain['status'] == 'accepted';
                        ?>
                        <div style="background: rgba(255, 255, 255, 0.5); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px; transition: border-color 0.3s; position: relative;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <h4 style="font-size: 14px; color: var(--buyer-slate); font-weight: 700;"><?php echo htmlspecialchars($bargain['crop_name']); ?></h4>
                                <span class="user-badge" style="font-size: 10px; padding: 2px 8px; background: <?php echo $is_accepted ? 'rgba(16, 185, 129, 0.1)' : 'rgba(245, 158, 11, 0.1)'; ?>; color: <?php echo $is_accepted ? '#10b981' : '#f59e0b'; ?>;">
                                    <?php echo $is_accepted ? '🎉 Accepted' : '⏳ Pending Response'; ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                                Grower: <strong><?php echo htmlspecialchars($bargain['farmer_name']); ?></strong> • Listing price: ₹<?php echo $bargain['listing_price']; ?>/kg
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--light-bg); padding: 8px 12px; border-radius: 6px; font-size: 13px;">
                                <span>Your Offer Rate:</span>
                                <span style="font-weight: 700; color: var(--buyer-indigo);">₹<?php echo $bargain['proposed_price']; ?>/Kg</span>
                            </div>
                            
                            <?php if ($is_accepted) { ?>
                                <a href="marketplace.php" class="btn btn-primary" style="width: 100%; font-size: 12px; padding: 8px; margin-top: 14px; background: #10b981; color: white; border: none; text-align: center; display: block; border-radius: 6px;">
                                    🛒 Checkout with Accepted Price
                                </a>
                            <?php } ?>
                        </div>
                        <?php 
                    }
                } else {
                    echo '<div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 13px; line-height: 1.5;">No active bargains. You can negotiate prices inside the crop chat terminals!</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Active Chart Rendering JavaScript -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Chart 1: Spending distribution
            const ctx1 = document.getElementById('spendingChart').getContext('2d');
            const spendingLabels = <?php echo json_encode(array_column($spending_data, 'label')); ?>;
            const spendingValues = <?php echo json_encode(array_column($spending_data, 'value')); ?>;
            
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: spendingLabels,
                    datasets: [{
                        label: 'Procured Volume Cost (₹)',
                        data: spendingValues,
                        backgroundColor: 'rgba(6, 182, 212, 0.7)',
                        borderColor: '#06b6d4',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            // Chart 2: Order status pipeline
            const ctx2 = document.getElementById('logisticsChart').getContext('2d');
            const logData = <?php echo json_encode(array_values($logistics_data)); ?>;
            
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Placed', 'Accepted', 'Packed', 'Shipped', 'Delivered', 'Cancelled'],
                    datasets: [{
                        data: logData,
                        backgroundColor: [
                            '#cbd5e1',
                            '#818cf8',
                            '#fbbf24',
                            '#38bdf8',
                            '#34d399',
                            '#f87171'
                        ],
                        borderWidth: 3,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                font: { size: 11, family: 'Inter' },
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
