<?php
session_start();
include("config/db.php");

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '';

// Mock MSP details for the analytics table
$msp_data = [
    ['name' => '<i class="ph-duotone ph-plant"></i> Wheat (Kanak)', 'msp' => '2275', 'portal' => '2350', 'status' => 'High Demand', 'color' => 'var(--primary-hover)'],
    ['name' => '🍚 Paddy (Basmati)', 'msp' => '2203', 'portal' => '2410', 'status' => 'Surge', 'color' => '#3b82f6'],
    ['name' => '🥔 Potato (Kufri)', 'msp' => '1200', 'portal' => '1380', 'status' => 'Stable', 'color' => 'var(--warning)'],
    ['name' => '🍅 Tomato (Desi)', 'msp' => '1500', 'portal' => '1890', 'status' => 'Oversupply', 'color' => 'var(--danger)'],
    ['name' => '🧅 Onion (Red)', 'msp' => '1800', 'portal' => '1950', 'status' => 'Stable', 'color' => 'var(--secondary)']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live MSP & Market Prices | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=2.0">
    
    <style>
        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        
        .price-table th, .price-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .price-table th {
            background: var(--light-bg);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .price-table tr:last-child td {
            border-bottom: none;
        }
        
        .metric-comparison {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <!-- Header bar dynamically adjusting to session -->
    <header class="navbar">
        <a href="index.php" class="navbar-brand">
            <span><i class="ph-duotone ph-plant"></i></span> AgroNava
        </a>
        <button class="navbar-toggle" id="navbar-toggle-btn" aria-label="Toggle navigation">
            <span>☰</span>
        </button>
        <div class="navbar-menu" id="navbar-menu-container">
            <a href="market_prices.php" style="color: var(--secondary); font-weight: 700;">Live Prices</a>
            <?php if($role == 'farmer') { ?>
                <a href="farmer/dashboard.php" style="color: var(--text-muted); font-weight: 600;">My Listings</a>
                <div class="user-badge">👨‍<i class="ph-duotone ph-plant"></i> <?php echo htmlspecialchars($name); ?></div>
            <?php } else if($role == 'buyer') { ?>
                <a href="buyer/marketplace.php" style="color: var(--text-muted); font-weight: 600;">Marketplace</a>
                <div class="user-badge"><i class="ph-duotone ph-shopping-cart"></i> <?php echo htmlspecialchars($name); ?></div>
            <?php } else { ?>
                <a href="auth/login.php" class="btn btn-secondary">Sign In</a>
                <a href="auth/register.php" class="btn btn-primary">Join Now</a>
            <?php } ?>
        </div>
    </header>

    <div class="grid-container animate-fade">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <div>
                <h1 style="font-size: 32px; color: var(--dark);">Market Price Index Tracking</h1>
                <p style="color: var(--text-muted);">Compare current government Minimum Support Price (MSP) vs AgroNava Direct Trade Averages</p>
            </div>
            
            <?php if($role == 'farmer') { ?>
                <a href="farmer/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            <?php } else if($role == 'buyer') { ?>
                <a href="buyer/marketplace.php" class="btn btn-secondary">← Back to Marketplace</a>
            <?php } else { ?>
                <a href="index.php" class="btn btn-secondary">← Back Home</a>
            <?php } ?>
        </div>

        <!-- Price Analytics Interactive SVG Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <div>
                    <h3 style="font-size: 18px; color: var(--dark);"><i class="ph-duotone ph-chart-line-up"></i> Consolidated MSP Price Trends (2026 Index)</h3>
                    <p style="color: var(--text-muted); font-size: 13px;">Fluctuation analytics of core commodity rates (₹ per Quintal)</p>
                </div>
                <div style="text-align: right;">
                    <div id="tooltip-price" style="font-size: 18px; font-weight: 800; color: var(--secondary);">₹2,350/Quintal</div>
                    <div id="tooltip-date" style="font-size: 12px; color: var(--text-muted);">Trend for Sun</div>
                </div>
            </div>
            
            <svg class="chart-svg" id="market-price-svg" viewBox="0 0 1000 200" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="chart-gradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.4"/>
                        <stop offset="100%" stop-color="var(--primary)" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <line x1="50" y1="30" x2="950" y2="30" stroke="#f1f5f9" stroke-width="1.5" />
                <line x1="50" y1="85" x2="950" y2="85" stroke="#f1f5f9" stroke-width="1.5" />
                <line x1="50" y1="140" x2="950" y2="140" stroke="#f1f5f9" stroke-width="1.5" />
                <line x1="50" y1="170" x2="950" y2="170" stroke="#cbd5e1" stroke-width="1.5" />
                
                <path id="chart-area-path" class="chart-area" d="" />
                <path id="chart-line-path" class="chart-line" d="" />
                <g id="chart-points-group"></g>
            </svg>
        </div>

        <!-- Comparative MSP Listing Board -->
        <h2 style="font-size: 22px; color: var(--dark); margin-bottom: 8px;"><i class="ph-duotone ph-plant"></i> MSP rates vs AgroNava Direct Rates</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Direct AgroNava trade cuts out commissions, netting farmers higher profits.</p>

        <table class="price-table animate-slide">
            <thead>
                <tr>
                    <th>Crop Produce Type</th>
                    <th>Govt MSP Rate (₹/Qtl)</th>
                    <th>AgroNava Rate (₹/Qtl)</th>
                    <th>Price Margin Delta</th>
                    <th>Demand Sentiment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($msp_data as $row) { 
                    $delta = (int)$row['portal'] - (int)$row['msp'];
                ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--dark);"><?php echo $row['name']; ?></td>
                        <td style="color: var(--text-muted); font-weight: 600;">₹<?php echo number_format($row['msp']); ?></td>
                        <td style="color: var(--secondary); font-weight: 700;">₹<?php echo number_format($row['portal']); ?></td>
                        <td>
                            <span style="color: var(--primary-hover); font-weight: 700;">
                                +₹<?php echo $delta; ?>/Qtl (+<?php echo round(($delta/$row['msp'])*100, 1); ?>%)
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: <?php echo $row['color']; ?>; font-size: 11px;">
                                ● <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    </div>

    <!-- Scripting integration -->
    <script src="assets/js/app.js"></script>
</body>
</html>
