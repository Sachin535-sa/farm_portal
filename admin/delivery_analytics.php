<?php
session_start();
include("../config/db.php");

// Query aggregate logistics metrics from database
$agg_q = mysqli_query($conn, "SELECT 
    AVG(delivery_distance) as avg_dist, 
    AVG(transport_cost) as avg_cost, 
    COUNT(*) as total_deliveries, 
    SUM(fuel_adjustment) as total_fuel_expenses, 
    SUM(transport_cost) as total_revenue,
    SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) as delivered_count,
    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM orders WHERE transport_cost > 0");

$agg = mysqli_fetch_assoc($agg_q);
$avg_distance = $agg['avg_dist'] ?? 0.0;
$avg_cost = $agg['avg_cost'] ?? 0.0;
$total_deliveries = $agg['total_deliveries'] ?? 0;
$total_fuel = $agg['total_fuel_expenses'] ?? 0.0;
$total_revenue = $agg['total_revenue'] ?? 0.0;
$delivered_count = $agg['delivered_count'] ?? 0;
$cancelled_count = $agg['cancelled_count'] ?? 0;

$success_rate = $total_deliveries > 0 ? round(($delivered_count / $total_deliveries) * 100, 1) : 100.0;

// Query returns
$returns_q = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(total_return_cost) as cost FROM return_logistics");
$returns_info = mysqli_fetch_assoc($returns_q);
$return_count = $returns_info['count'] ?? 0;
$return_expenses = $returns_info['cost'] ?? 0.0;

// Query Vehicle Usage distribution
$vehicle_usage = [];
$veh_q = mysqli_query($conn, "SELECT vehicle_type, COUNT(*) as count FROM orders WHERE vehicle_type IS NOT NULL GROUP BY vehicle_type");
while($row = mysqli_fetch_assoc($veh_q)) {
    $vehicle_usage[] = [
        'label' => ucfirst(str_replace('_', ' ', $row['vehicle_type'])),
        'count' => (int)$row['count']
    ];
}
// Default data fallback for chart demonstration
if (empty($vehicle_usage)) {
    $vehicle_usage = [
        ['label' => '🛵 2-Wheeler', 'count' => 12],
        ['label' => '🛺 3-Wheeler', 'count' => 8],
        ['label' => '🚚 Mini Truck', 'count' => 15],
        ['label' => '🚛 Heavy Truck', 'count' => 5],
        ['label' => '⚡ Electric Vehicle', 'count' => 10]
    ];
}

// Query Zone distribution
$zone_distribution = [];
$zone_q = mysqli_query($conn, "SELECT delivery_zone, COUNT(*) as count FROM orders WHERE delivery_zone IS NOT NULL GROUP BY delivery_zone");
while($row = mysqli_fetch_assoc($zone_q)) {
    $zone_distribution[] = [
        'label' => ucfirst(str_replace('_', ' ', $row['delivery_zone'])),
        'count' => (int)$row['count']
    ];
}
if (empty($zone_distribution)) {
    $zone_distribution = [
        ['label' => 'Urban', 'count' => 24],
        ['label' => 'Rural', 'count' => 10],
        ['label' => 'Hill Area', 'count' => 6],
        ['label' => 'Remote Village', 'count' => 4],
        ['label' => 'Industrial Area', 'count' => 8]
    ];
}

// Most expensive routes
$routes_sql = "SELECT o.id, c.crop_name, o.delivery_distance, o.transport_cost, b.name as buyer_name, f.name as farmer_name 
               FROM orders o 
               JOIN crops c ON o.crop_id=c.id 
               JOIN users b ON o.buyer_id=b.id 
               JOIN users f ON c.farmer_id=f.id 
               WHERE o.transport_cost > 0
               ORDER BY o.transport_cost DESC LIMIT 5";
$expensive_routes = mysqli_query($conn, $routes_sql);

// Cheapest routes
$cheapest_sql = "SELECT o.id, c.crop_name, o.delivery_distance, o.transport_cost, b.name as buyer_name, f.name as farmer_name 
                 FROM orders o 
                 JOIN crops c ON o.crop_id=c.id 
                 JOIN users b ON o.buyer_id=b.id 
                 JOIN users f ON c.farmer_id=f.id 
                 WHERE o.transport_cost > 0
                 ORDER BY o.transport_cost ASC LIMIT 5";
$cheapest_routes = mysqli_query($conn, $cheapest_sql);

// Dispute stats
$complaints_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE status='Pending'");
$pending_complaints = mysqli_fetch_assoc($complaints_query)['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Logistics Analytics | AgroNava</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            max-width: 1300px;
            margin: 0 auto;
        }

        .metric-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.01);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .metric-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 28px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: var(--dark);
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-bottom: 32px;
        }

        .chart-container {
            background: white;
            padding: 24px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.01);
        }

        .table-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="admin-sidebar">
        <a href="../index.php" class="sidebar-brand">
            <i class='ph-duotone ph-plant' style="color: var(--primary);"></i> AgroNava
        </a>
        
        <div class="sidebar-menu">
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; padding: 0 24px; display: block; margin-bottom: 8px;">Command Center</span>
            <a href="dashboard.php" class="sidebar-link">
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
            <a href="delivery_analytics.php" class="sidebar-link active">
                <i class='ph-duotone ph-chart-bar'></i> Delivery Analytics
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
                <h2 style="margin: 0; font-size: 20px; font-family: 'Outfit', sans-serif; color: var(--dark);">Logistics Analytics Center</h2>
                <span style="font-size: 13px; color: var(--text-muted);">Visual summaries of transportation revenues, dynamic diesel factors, and delivery routing metrics</span>
            </div>
            <div class="user-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                <span>📊</span> Live Dashboard
            </div>
        </div>

        <div class="admin-content animate-fade">
            <!-- Aggregated Metrics Deck -->
            <div class="grid-4" style="margin-bottom: 32px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-truck"></i> Total Deliveries</span>
                    <span class="metric-value"><?php echo number_format($total_deliveries); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-currency-circle-dollar"></i> Logistics Revenue</span>
                    <span class="metric-value" style="color: var(--primary);">₹<?php echo number_format($total_revenue, 2); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-gas-pump"></i> Fuel Expense Pool</span>
                    <span class="metric-value" style="color: #ef4444;">₹<?php echo number_format($total_fuel, 2); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-arrows-left-right"></i> Avg Distance / Trip</span>
                    <span class="metric-value"><?php echo number_format($avg_distance, 1); ?> km</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-check-square"></i> Success Rate</span>
                    <span class="metric-value" style="color: #10b981;"><?php echo $success_rate; ?>%</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-arrow-counter-clockwise"></i> Return Logistics</span>
                    <span class="metric-value"><?php echo $return_count; ?> rejects</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-credit-card"></i> Return Cost pool</span>
                    <span class="metric-value" style="color: var(--secondary);">₹<?php echo number_format($return_expenses, 2); ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="ph-duotone ph-calculator"></i> Avg Cost / Order</span>
                    <span class="metric-value">₹<?php echo number_format($avg_cost, 2); ?></span>
                </div>
            </div>

            <!-- Interactive Chart Grid -->
            <div class="chart-grid">
                <div class="chart-container">
                    <h3 style="font-size: 16px; font-family: 'Outfit', sans-serif; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;"><i class="ph-duotone ph-moped" style="color: var(--primary);"></i> Vehicle Fleet Usage Profile</h3>
                    <canvas id="vehicleChart" style="max-height: 250px;"></canvas>
                </div>
                <div class="chart-container">
                    <h3 style="font-size: 16px; font-family: 'Outfit', sans-serif; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;"><i class="ph-duotone ph-map-pin" style="color: var(--secondary);"></i> Destination Zone Metrics</h3>
                    <canvas id="zoneChart" style="max-height: 250px;"></canvas>
                </div>
            </div>

            <!-- Ledger Tables for expensive/cheapest routes -->
            <div class="table-grid">
                <div class="chart-container">
                    <h3 style="font-size: 16px; font-family: 'Outfit', sans-serif; margin-bottom: 16px; display: flex; align-items: center; gap: 6px; color: #ef4444;"><i class="ph-duotone ph-arrow-trend-up"></i> Most Expensive Logistics Routes</h3>
                    <table class="ledger-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Distance</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$expensive_routes || mysqli_num_rows($expensive_routes) == 0): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted);">No routes recorded.</td>
                            </tr>
                            <?php else: ?>
                                <?php while($r = mysqli_fetch_assoc($expensive_routes)): ?>
                                <tr>
                                    <td>#<?php echo $r['id']; ?>: <?php echo htmlspecialchars($r['crop_name']); ?></td>
                                    <td><?php echo $r['delivery_distance']; ?> km</td>
                                    <td style="font-weight: 700; color: #ef4444;">₹<?php echo $r['transport_cost']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="chart-container">
                    <h3 style="font-size: 16px; font-family: 'Outfit', sans-serif; margin-bottom: 16px; display: flex; align-items: center; gap: 6px; color: #10b981;"><i class="ph-duotone ph-arrow-trend-down"></i> Most Economical Logistics Routes</h3>
                    <table class="ledger-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Distance</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$cheapest_routes || mysqli_num_rows($cheapest_routes) == 0): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted);">No routes recorded.</td>
                            </tr>
                            <?php else: ?>
                                <?php while($r = mysqli_fetch_assoc($cheapest_routes)): ?>
                                <tr>
                                    <td>#<?php echo $r['id']; ?>: <?php echo htmlspecialchars($r['crop_name']); ?></td>
                                    <td><?php echo $r['delivery_distance']; ?> km</td>
                                    <td style="font-weight: 700; color: #10b981;">₹<?php echo $r['transport_cost']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Configuration Script -->
    <script>
        // 1. Vehicle Chart
        const vehCtx = document.getElementById('vehicleChart').getContext('2d');
        new Chart(vehCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($vehicle_usage, 'label')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($vehicle_usage, 'count')); ?>,
                    backgroundColor: [
                        '#10b981', '#06b6d4', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#10b981'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // 2. Zone Chart
        const zoneCtx = document.getElementById('zoneChart').getContext('2d');
        new Chart(zoneCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($zone_distribution, 'label')); ?>,
                datasets: [{
                    label: 'Trips by Zone',
                    data: <?php echo json_encode(array_column($zone_distribution, 'count')); ?>,
                    backgroundColor: 'rgba(15, 118, 110, 0.75)',
                    borderColor: '#0f766e',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    </script>
</body>
</html>
