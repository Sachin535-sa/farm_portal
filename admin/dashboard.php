<?php
session_start();
include("../config/db.php");

// Premium demonstration: We allow access for demo purposes, but in reality we'd strict check:
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { ... }

// Aggregates
$farmers_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='farmer'");
$farmers_count = mysqli_fetch_assoc($farmers_query)['c'];

$buyers_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='buyer'");
$buyers_count = mysqli_fetch_assoc($buyers_query)['c'];

// Only active or completed orders volume (exclude cancelled)
$vol_query = mysqli_query($conn, "SELECT SUM((quantity * price) + transport_cost) as vol FROM orders WHERE status != 'cancelled'");
$total_volume = mysqli_fetch_assoc($vol_query)['vol'] ?? 0;

$complaints_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE status='Pending'");
$pending_complaints = mysqli_fetch_assoc($complaints_query)['c'];

// Recent Transactions Ledger
$recent_orders_sql = "SELECT o.*, c.crop_name, b.name as buyer_name, f.name as farmer_name
                      FROM orders o
                      JOIN crops c ON o.crop_id = c.id
                      JOIN users b ON o.buyer_id = b.id
                      JOIN users f ON c.farmer_id = f.id
                      ORDER BY o.created_at DESC LIMIT 8";
$recent_orders = mysqli_query($conn, $recent_orders_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Command Center | AgroNava</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body {
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Unified Admin Sidebar */
        .admin-sidebar {
            width: 260px;
            background: #0f172a;
            color: #94a3b8;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-brand {
            padding: 24px;
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .sidebar-link:hover, .sidebar-link.active {
            background: rgba(255,255,255,0.03);
            color: white;
            border-left: 3px solid var(--primary);
        }

        .sidebar-link i {
            font-size: 20px;
        }

        .sidebar-footer {
            padding: 20px 24px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* Main Content Area */
        .admin-main {
            flex: 1;
            overflow-y: auto;
            background: #f8fafc;
        }

        .admin-header {
            background: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .admin-content {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .metric-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .metric-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 32px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: var(--dark);
        }

        .ledger-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ledger-table th, .ledger-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .ledger-table th {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--light-bg);
        }
        .ledger-table td {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }
    </style>
</head>
<body>

    <!-- Unified Sidebar -->
    <div class="admin-sidebar">
        <a href="../index.php" class="sidebar-brand">
            <i class='ph-duotone ph-plant' style="color: var(--primary);"></i> AgroNava
        </a>
        
        <div class="sidebar-menu">
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; padding: 0 24px; display: block; margin-bottom: 8px;">Command Center</span>
            <a href="dashboard.php" class="sidebar-link active">
                <i class='ph-duotone ph-squares-four'></i> Platform Overview
            </a>
            <a href="../admin_complaints.php" class="sidebar-link">
                <i class='ph-duotone ph-shield-check'></i> Dispute & Claims
                <?php if ($pending_complaints > 0) { ?>
                    <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 20px; font-size: 11px; margin-left: auto;"><?php echo $pending_complaints; ?></span>
                <?php } ?>
            </a>
            <a href="delivery_pricing.php" class="sidebar-link">
                <i class='ph-duotone ph-truck'></i> Delivery Pricing
            </a>
            <a href="#" class="sidebar-link">
                <i class='ph-duotone ph-users'></i> User Directory
            </a>
            <a href="#" class="sidebar-link">
                <i class='ph-duotone ph-bank'></i> Escrow Settlements
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="btn btn-secondary" style="width: 100%; justify-content: center; background: rgba(255,255,255,0.05); color: #94a3b8; border: none;">
                <i class='ph-duotone ph-sign-out'></i> Terminate Session
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-header">
            <div>
                <h2 style="margin: 0; font-size: 20px; font-family: 'Outfit', sans-serif; color: var(--dark);">Platform Overview</h2>
                <span style="font-size: 13px; color: var(--text-muted);">Aggregated metrics across all ecosystem nodes</span>
            </div>
            <div class="user-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                <span>👑</span> System Administrator
            </div>
        </div>
        
        <div class="admin-content animate-fade">
            <!-- Top Metrics -->
            <div class="grid-4" style="margin-bottom: 32px;">
                <div class="metric-card">
                    <span class="metric-label"><i class='ph-duotone ph-plant'></i> Registered Growers</span>
                    <span class="metric-value"><?php echo number_format($farmers_count); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class='ph-duotone ph-shopping-cart'></i> Wholesale Buyers</span>
                    <span class="metric-value"><?php echo number_format($buyers_count); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class='ph-duotone ph-vault'></i> Escrow Volume (₹)</span>
                    <span class="metric-value" style="color: var(--primary);">₹<?php echo number_format($total_volume); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class='ph-duotone ph-warning-circle'></i> Active Disputes</span>
                    <span class="metric-value" style="color: <?php echo ($pending_complaints > 0) ? '#ef4444' : '#10b981'; ?>;">
                        <?php echo number_format($pending_complaints); ?>
                    </span>
                </div>
            </div>
            
            <!-- Global Transactions Ledger -->
            <div class="glass-card" style="background: white;">
                <div style="padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 18px; font-family: 'Outfit', sans-serif;"><i class='ph-duotone ph-arrows-left-right'></i> Global Transactions Ledger</h3>
                    <button class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">Export CSV</button>
                </div>
                
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Crop / Asset</th>
                            <th>Grower</th>
                            <th>Buyer</th>
                            <th>Gross Value</th>
                            <th>Status</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($recent_orders) > 0) { 
                            while ($row = mysqli_fetch_assoc($recent_orders)) {
                                $val = ($row['quantity'] * $row['price']) + $row['transport_cost'];
                        ?>
                        <tr>
                            <td style="font-weight: 700; color: var(--secondary);">#<?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['crop_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['farmer_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['buyer_name']); ?></td>
                            <td style="font-weight: 700;">₹<?php echo number_format($val); ?></td>
                            <td>
                                <?php
                                $s = strtolower($row['status']);
                                $b_class = 'badge-pending';
                                if ($s == 'delivered') $b_class = 'badge-delivered';
                                if ($s == 'cancelled') $b_class = 'badge-cancelled';
                                echo "<span class='badge {$b_class}'>" . ucfirst($s) . "</span>";
                                ?>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?php echo date("M j, Y H:i", strtotime($row['created_at'])); ?></td>
                        </tr>
                        <?php } } else { ?>
                            <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No global transactions recorded yet.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</body>
</html>
