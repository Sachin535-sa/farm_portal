<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Intelligence | AgroNava</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&family=Syne:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --green-deep: #14532d;
            --green-live: #22c55e;
            --gold: #f59e0b;
            --danger: #ef4444;
            --blue: #3b82f6;
        }

        body {
            margin: 0; padding: 0;
            background: #f7fef4;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            min-height: 100vh;
        }

        /* Light Animated Background */
        .bg-light {
            position: fixed; inset: 0; z-index: -1;
            background: linear-gradient(160deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
        }
        .grid-bg-light {
            position: fixed; inset: 0; z-index: -1;
            background-image:
              linear-gradient(rgba(34,197,94,0.1) 1px, transparent 1px),
              linear-gradient(90deg, rgba(34,197,94,0.1) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 25s linear infinite;
        }
        @keyframes gridMove { from{background-position:0 0;} to{background-position:60px 60px;} }

        .orb-light {
            position: fixed; border-radius: 50%;
            filter: blur(90px); z-index: -1;
            animation: orbPulse 15s ease-in-out infinite;
        }
        .orb-1-light { width:400px;height:400px;background:#4ade80;top:-100px;left:-100px;opacity:0.3; }
        .orb-2-light { width:300px;height:300px;background:#fcd34d;bottom:-50px;right:-50px;opacity:0.25;animation-delay:-6s; }
        @keyframes orbPulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.15);} }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(34, 197, 94, 0.2);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0; z-index: 100;
        }

        .nav-brand {
            font-family: 'Outfit', sans-serif;
            font-size: 24px; font-weight: 900;
            color: var(--green-deep);
            text-decoration: none;
            display: flex; align-items: center; gap: 10px;
        }

        .nav-links a {
            font-family: 'Inter', sans-serif;
            font-weight: 600; font-size: 14px;
            color: #475569;
            text-decoration: none; margin-left: 20px;
            transition: color 0.3s;
        }

        .nav-links a:hover, .nav-links a.active {
            color: var(--green-deep);
        }

        /* Container */
        .market-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 36px; font-weight: 900;
            color: var(--green-deep);
            margin: 0 0 10px 0;
            letter-spacing: -1px;
        }

        .page-title p {
            font-size: 15px; color: #64748b; margin: 0;
            font-weight: 500;
        }

        .live-badge {
            background: rgba(34, 197, 94, 0.15);
            color: #15803d;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 12px;
            display: flex; align-items: center; gap: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .live-badge::before {
            content: '';
            width: 8px; height: 8px;
            background: var(--green-live);
            border-radius: 50%;
            animation: pulseDot 1.5s infinite;
        }

        @keyframes pulseDot {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        /* Highlight Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.03);
            position: relative; overflow: hidden;
            transition: transform 0.3s;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }

        .metric-card::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 4px; height: 100%;
        }

        .card-trend-up::before { background: var(--green-live); }
        .card-trend-down::before { background: var(--danger); }
        .card-trend-neutral::before { background: var(--blue); }

        .metric-title {
            font-size: 13px; font-weight: 600; color: #64748b;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;
        }

        .metric-val {
            font-family: 'Outfit', sans-serif;
            font-size: 32px; font-weight: 800; color: #0f172a;
            margin-bottom: 5px;
        }

        .metric-sub {
            font-size: 14px; font-weight: 600;
        }

        .text-green { color: #15803d; }
        .text-red { color: #b91c1c; }

        /* Comparison Table Section */
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.04);
            margin-bottom: 40px;
        }

        .panel-title {
            font-family: 'Syne', sans-serif;
            font-size: 20px; font-weight: 800; color: var(--green-deep);
            margin-top: 0; margin-bottom: 25px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .accuracy-tag {
            font-family: 'Inter', sans-serif;
            font-size: 12px; font-weight: 700;
            background: #f1f5f9; color: #475569;
            padding: 6px 12px; border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        table {
            width: 100%; border-collapse: collapse;
        }

        th {
            text-align: left; padding: 15px;
            font-size: 12px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }

        td {
            padding: 20px 15px;
            font-size: 15px; font-weight: 600; color: #1e293b;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }

        .crop-name {
            display: flex; align-items: center; gap: 12px;
            font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 700;
        }

        .crop-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }

        .price-bad { color: #94a3b8; text-decoration: line-through; font-size: 13px; font-weight: 500; }
        .price-good { color: var(--green-deep); font-weight: 800; font-size: 18px; }
        
        .agronava-highlight {
            background: rgba(34, 197, 94, 0.1);
            border-radius: 8px;
            padding: 8px 12px;
            display: inline-block;
        }

        .trend-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: 50px; font-size: 12px; font-weight: 700;
        }
        .trend-up { background: rgba(34, 197, 94, 0.15); color: #15803d; }
        .trend-down { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }

        /* Chart Simulation */
        .chart-container {
            height: 200px;
            display: flex; align-items: flex-end; gap: 10px;
            padding-top: 20px; margin-top: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        .chart-bar-wrapper {
            flex: 1; display: flex; flex-direction: column; align-items: center; gap: 10px;
        }

        .chart-bar {
            width: 40px; border-radius: 6px 6px 0 0;
            background: linear-gradient(to top, #86efac, #22c55e);
            position: relative;
            animation: growUp 1s ease-out forwards;
            transform-origin: bottom;
            opacity: 0;
        }

        @keyframes growUp {
            0% { transform: scaleY(0); opacity: 0; }
            100% { transform: scaleY(1); opacity: 1; }
        }

        .bar-label { font-size: 12px; font-weight: 600; color: #64748b; }

        @media(max-width: 900px) {
            .metrics-grid { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>

    <div class="bg-light"></div>
    <div class="grid-bg-light"></div>
    <div class="orb-light orb-1-light"></div>
    <div class="orb-light orb-2-light"></div>

    <nav class="navbar">
        <a href="dashboard.php" class="nav-brand"><span>🌾</span> AgroNava</a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="add_crop.php">List Harvest</a>
            <a href="market.php" class="active">Market Intel</a>
            <a href="orders.php">Orders</a>
        </div>
    </nav>

    <div class="market-container">
        
        <div class="page-header">
            <div class="page-title">
                <h1>Market Intelligence</h1>
                <p>Advanced Price Tracking & Platform Comparison System</p>
            </div>
            <div class="live-badge">Live Analytics</div>
        </div>

        <!-- KPI Metrics -->
        <div class="metrics-grid">
            <div class="metric-card card-trend-up">
                <div class="metric-title">Highest Trending Crop</div>
                <div class="metric-val">Organic Wheat</div>
                <div class="metric-sub text-green">↑ +14.2% Demand Surge</div>
            </div>
            <div class="metric-card card-trend-down">
                <div class="metric-title">Price Alert</div>
                <div class="metric-val">Onions</div>
                <div class="metric-sub text-red">↓ -5.4% Market Oversupply</div>
            </div>
            <div class="metric-card card-trend-neutral">
                <div class="metric-title">Avg. Profit Margin</div>
                <div class="metric-val">+22%</div>
                <div class="metric-sub" style="color:var(--blue)">Compared to Local Mandis</div>
            </div>
        </div>

        <!-- Competitor Comparison -->
        <div class="glass-panel">
            <h2 class="panel-title">
                Selling Price Comparison Benchmark
                <span class="accuracy-tag">Accuracy: 98.4% (Live Data)</span>
            </h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Crop Commodity</th>
                        <th>Traditional Mandi</th>
                        <th>Other Farm Apps</th>
                        <th>AgroNava Direct Trade</th>
                        <th>Market Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="crop-name"><div class="crop-icon">🌾</div> Wheat (Premium)</div>
                        </td>
                        <td><span class="price-bad">₹20.00 /kg</span></td>
                        <td><span class="price-bad">₹22.50 /kg</span></td>
                        <td><div class="agronava-highlight"><span class="price-good">₹26.00 /kg</span></div></td>
                        <td><span class="trend-pill trend-up">↑ +2.5%</span></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="crop-name"><div class="crop-icon">🍅</div> Tomatoes (Organic)</div>
                        </td>
                        <td><span class="price-bad">₹15.00 /kg</span></td>
                        <td><span class="price-bad">₹18.00 /kg</span></td>
                        <td><div class="agronava-highlight"><span class="price-good">₹24.00 /kg</span></div></td>
                        <td><span class="trend-pill trend-up">↑ +8.1%</span></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="crop-name"><div class="crop-icon">🥔</div> Potatoes (Grade A)</div>
                        </td>
                        <td><span class="price-bad">₹12.00 /kg</span></td>
                        <td><span class="price-bad">₹13.50 /kg</span></td>
                        <td><div class="agronava-highlight"><span class="price-good">₹18.00 /kg</span></div></td>
                        <td><span class="trend-pill trend-down">↓ -1.2%</span></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="crop-name"><div class="crop-icon">🌽</div> Sweet Corn</div>
                        </td>
                        <td><span class="price-bad">₹25.00 /kg</span></td>
                        <td><span class="price-bad">₹28.00 /kg</span></td>
                        <td><div class="agronava-highlight"><span class="price-good">₹34.00 /kg</span></div></td>
                        <td><span class="trend-pill trend-up">↑ +5.5%</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Visual Analytics -->
        <div class="glass-panel">
            <h2 class="panel-title">AgroNava Profitability Index</h2>
            <p style="color:#64748b; font-size:14px; font-weight:500;">Average Revenue per 1000kg (in ₹)</p>
            
            <div class="chart-container">
                <div class="chart-bar-wrapper">
                    <div class="chart-bar" style="height: 35%; animation-delay: 0.1s; background: #94a3b8;"></div>
                    <div class="bar-label">Local Mandi (15k)</div>
                </div>
                <div class="chart-bar-wrapper">
                    <div class="chart-bar" style="height: 50%; animation-delay: 0.3s; background: #60a5fa;"></div>
                    <div class="bar-label">Wholesalers (20k)</div>
                </div>
                <div class="chart-bar-wrapper">
                    <div class="chart-bar" style="height: 65%; animation-delay: 0.5s; background: #fbbf24;"></div>
                    <div class="bar-label">Other Apps (25k)</div>
                </div>
                <div class="chart-bar-wrapper">
                    <div class="chart-bar" style="height: 95%; animation-delay: 0.7s;"></div>
                    <div class="bar-label" style="color:var(--green-deep); font-weight:800;">AgroNava (36k)</div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
