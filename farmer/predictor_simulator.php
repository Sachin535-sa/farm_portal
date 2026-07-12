<?php
session_start();
include("../config/db.php");

// Session validation
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "farmer"){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Crop Yield & Price Predictor | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css?v=1.6">
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .predictor-container {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 32px;
            margin-top: 24px;
        }
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px;
            box-shadow: var(--shadow-md);
        }
        
        .slider-group {
            margin-bottom: 24px;
        }
        
        .slider-label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .range-slider {
            width: 100%;
            height: 6px;
            background: #cbd5e1;
            border-radius: 50px;
            outline: none;
            -webkit-appearance: none;
            cursor: pointer;
        }
        
        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-hover);
            border: 3px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: transform 0.2s;
        }
        
        .range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.15);
        }
        
        .metric-card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .mini-card {
            background: var(--light-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .mini-val {
            font-size: 22px;
            font-weight: 800;
            color: var(--secondary);
            font-family: 'Outfit', sans-serif;
        }
        
        .mini-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body style="background: #f1f5f9; min-height: 100vh;">

    <!-- Header bar -->
    <header class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span>🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <a href="dashboard.php" style="color: var(--text-muted); font-weight: 600;">My Listings</a>
            <a href="orders.php" style="color: var(--text-muted); font-weight: 600;">Manage Orders</a>
            <a href="../market_prices.php" style="color: var(--text-muted); font-weight: 600;">Live Prices</a>
            <div class="user-badge">
                <span>👨‍🌾</span> <?php echo htmlspecialchars($_SESSION['name']); ?>
            </div>
            <a class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;" href="dashboard.php">← Back to Dashboard</a>
        </div>
    </header>

    <div class="grid-container animate-fade">
        <div style="margin-bottom: 24px;">
            <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; color: var(--primary-hover); background: var(--primary-light); padding: 4px 12px; border-radius: 50px; display: inline-block; margin-bottom: 8px;">🧠 Predictive Agro-Modeling</span>
            <h1 style="font-size: 32px; color: var(--dark); margin: 0;">Smart Yield & Price Predictor</h1>
            <p style="color: var(--text-muted);">Simulate agricultural crop yields, expected marketplace valuations, and net margins based on real soil/input metrics.</p>
        </div>

        <div class="predictor-container">
            <!-- Left Side: Simulator Inputs -->
            <div class="glass-panel animate-slide">
                <h3 style="font-size: 18px; color: var(--dark); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border);">⚙️ Sowing Parameters</h3>
                
                <!-- Input 1: Crop Selection -->
                <div class="form-group">
                    <label class="form-label" for="sim-crop">🌾 Sown Produce Type</label>
                    <select id="sim-crop" class="form-control" style="background-color: white;">
                        <option value="rice">🍚 Basmati Paddy (Rice)</option>
                        <option value="wheat" selected>🌾 Sharbati Wheat (Kanak)</option>
                        <option value="potatoes">🥔 Kufri Jyoti Potatoes</option>
                        <option value="tomatoes">🍅 Fresh Desi Red Tomatoes</option>
                        <option value="mustard">🌱 Organic Mustard Seeds</option>
                    </select>
                </div>

                <!-- Input 2: Land Area in Acres -->
                <div class="slider-group">
                    <div class="slider-label-row">
                        <label class="form-label" style="margin: 0;">🚜 Cultivated Land Size</label>
                        <span style="font-weight: 700; color: var(--secondary); font-size: 14px;"><span id="val-acres">5</span> Acres</span>
                    </div>
                    <input type="range" id="sim-acres" class="range-slider" min="1" max="50" value="5">
                </div>

                <!-- Input 3: Soil Category -->
                <div class="form-group">
                    <label class="form-label" for="sim-soil">🪨 Soil Classification</label>
                    <select id="sim-soil" class="form-control" style="background-color: white;">
                        <option value="alluvial" selected>Alluvial Soil (Highly Fertile)</option>
                        <option value="clayey">Clayey Soil (High Water Holding)</option>
                        <option value="black">Black Loamy Soil (Ideal for Wheat)</option>
                        <option value="sandy">Sandy Soil (Poor Water Retention)</option>
                    </select>
                </div>

                <!-- Input 4: Irrigation Level -->
                <div class="form-group">
                    <label class="form-label" for="sim-irrigation">💧 Irrigation Frequency</label>
                    <select id="sim-irrigation" class="form-control" style="background-color: white;">
                        <option value="medium" selected>Standard Balanced Irrigation</option>
                        <option value="high">Heavy Automated Flooding</option>
                        <option value="low">Dry Rainfed Natural Sowing</option>
                    </select>
                </div>

                <!-- Input 5: Fertilizer Type -->
                <div class="form-group">
                    <label class="form-label" for="sim-fertilizer">🧪 Organic / Chemical Nutrients</label>
                    <select id="sim-fertilizer" class="form-control" style="background-color: white;">
                        <option value="balanced" selected>NPK Balanced Complex</option>
                        <option value="organic">100% Organic Compost (Eco-Grown)</option>
                        <option value="nitrogen">High-Nitrogen Chemical Urea</option>
                        <option value="none">Zero Added Input (Natural Fallow)</option>
                    </select>
                </div>
            </div>

            <!-- Right Side: Live Forecasting Stats & Curve Charts -->
            <div class="glass-panel animate-slide" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 18px; color: var(--dark); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border);">📊 Simulated Harvest Projection</h3>
                    
                    <!-- Projection Cards -->
                    <div class="metric-card-grid">
                        <div class="mini-card">
                            <span class="mini-label">Predicted Yield</span>
                            <span class="mini-val" id="res-yield">115.5 Qtl</span>
                        </div>
                        <div class="mini-card">
                            <span class="mini-label">Direct Trade Value</span>
                            <span class="mini-val" id="res-price" style="color: var(--primary-hover);">₹3,220 / Qtl</span>
                        </div>
                        <div class="mini-card">
                            <span class="mini-label">Govt Support (MSP)</span>
                            <span class="mini-val" id="res-msp" style="color: #64748b;">₹2,275 / Qtl</span>
                        </div>
                        <div class="mini-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(15, 118, 110, 0.05)); border-color: rgba(16, 185, 129, 0.25);">
                            <span class="mini-label" style="color: var(--primary-hover); font-weight: 800;">Estimated Profit</span>
                            <span class="mini-val" id="res-profit" style="color: var(--primary-hover);">₹3,71,910</span>
                        </div>
                    </div>
                </div>

                <!-- Live Updates Chart Area -->
                <div>
                    <h4 style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">📈 Dynamic input profit curve</h4>
                    <div style="position: relative; height: 230px; width: 100%;">
                        <canvas id="predictorCurveChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripting for live math simulations -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // UI Inputs
        const cropSelect = document.getElementById("sim-crop");
        const acresInput = document.getElementById("sim-acres");
        const acresVal = document.getElementById("val-acres");
        const soilSelect = document.getElementById("sim-soil");
        const irrigationSelect = document.getElementById("sim-irrigation");
        const fertilizerSelect = document.getElementById("sim-fertilizer");

        // UI Output Text Elements
        const outYield = document.getElementById("res-yield");
        const outPrice = document.getElementById("res-price");
        const outMsp = document.getElementById("res-msp");
        const outProfit = document.getElementById("res-profit");

        // Dynamic Chart Context
        const ctx = document.getElementById('predictorCurveChart').getContext('2d');
        let chartInstance = null;

        // Crop Database Baseline Values
        const baseCrop = {
            rice: { yield: 18, price: 6500, msp: 2203, cost: 25000 },
            wheat: { yield: 22, price: 2800, msp: 2275, cost: 18000 },
            potatoes: { yield: 120, price: 2200, msp: 1200, cost: 45000 },
            tomatoes: { yield: 80, price: 4500, msp: 1500, cost: 35000 },
            mustard: { yield: 10, price: 8500, msp: 1800, cost: 15000 }
        };

        function simulateAgronomy() {
            const cropKey = cropSelect.value;
            const acres = parseFloat(acresInput.value);
            acresVal.textContent = acres;

            const base = baseCrop[cropKey];

            // 1. Soil multiplier
            let soilMult = 1.0;
            switch(soilSelect.value) {
                case "alluvial": soilMult = 1.15; break;
                case "clayey": soilMult = 1.05; break;
                case "black": soilMult = 1.10; break;
                case "sandy": soilMult = 0.80; break;
            }

            // 2. Irrigation multiplier
            let irrMult = 1.0;
            switch(irrigationSelect.value) {
                case "medium": irrMult = 1.05; break;
                case "low": irrMult = 0.85; break;
                case "high":
                    // Wheat and Mustard decay if flooded
                    if (cropKey === 'wheat' || cropKey === 'mustard') {
                        irrMult = 0.95;
                    } else {
                        irrMult = 1.12;
                    }
                    break;
            }

            // 3. Fertilizer multiplier
            let fertMult = 1.0;
            let fertCostFactor = 1.0;
            switch(fertilizerSelect.value) {
                case "balanced": fertMult = 1.20; fertCostFactor = 1.25; break;
                case "organic": fertMult = 1.12; fertCostFactor = 1.10; break; // cheaper but high premiums
                case "nitrogen": fertMult = 1.18; fertCostFactor = 1.35; break;
                case "none": fertMult = 0.90; fertCostFactor = 0.60; break;
            }

            // Calculations
            const predictedYieldPerAcre = base.yield * soilMult * irrMult * fertMult;
            const totalYield = predictedYieldPerAcre * acres;

            // Direct trade rate enjoys a +15% premium if listed as organic compost!
            let premiumFactor = 1.0;
            if (fertilizerSelect.value === 'organic') {
                premiumFactor = 1.15;
            }
            const expectedPrice = Math.round(base.price * premiumFactor);

            // Costs
            const totalCost = base.cost * fertCostFactor * acres;
            const totalRevenue = totalYield * expectedPrice;
            const netProfit = Math.max(0, totalRevenue - totalCost);

            // Update UI values
            outYield.textContent = `${totalYield.toFixed(1)} Qtl`;
            outPrice.textContent = `₹${expectedPrice.toLocaleString('en-IN')} / Qtl`;
            outMsp.textContent = `₹${base.msp.toLocaleString('en-IN')} / Qtl`;
            outProfit.textContent = `₹${Math.round(netProfit).toLocaleString('en-IN')}`;

            // Draw Profit Curve Chart
            updateProfitChartCurve(cropKey, soilMult, irrMult, fertCostFactor, expectedPrice, base.cost);
        }

        function updateProfitChartCurve(cropKey, soilMult, irrMult, fertCostFactor, expectedPrice, baseCost) {
            const curveLabels = [];
            const curveDataDirect = [];
            const curveDataMsp = [];

            // Compute expected profit curves from 1 to 20 acres dynamically
            for (let testAcres = 1; testAcres <= 10; testAcres++) {
                curveLabels.push(`${testAcres} Ac`);
                
                // Direct Trade Profit
                let fertMultBalanced = 1.20; // benchmark
                let y = baseCrop[cropKey].yield * soilMult * irrMult * fertMultBalanced * testAcres;
                let cost = baseCost * fertCostFactor * testAcres;
                let revDirect = y * expectedPrice;
                let profDirect = Math.max(0, revDirect - cost);
                curveDataDirect.push(Math.round(profDirect));

                // Government Support Rate (MSP) Profit (no organic premiums)
                let revMsp = y * baseCrop[cropKey].msp;
                let profMsp = Math.max(0, revMsp - cost);
                curveDataMsp.push(Math.round(profMsp));
            }

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: curveLabels,
                    datasets: [
                        {
                            label: 'Direct Trade Profits (AgroNava)',
                            data: curveDataDirect,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Govt MSP Profits (Wholesale)',
                            data: curveDataMsp,
                            borderColor: '#64748b',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 10, font: { size: 10 } } }
                    },
                    scales: {
                        y: {
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { font: { size: 9 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 9 } }
                        }
                    }
                }
            });
        }

        // Hook Event Listeners
        cropSelect.addEventListener("change", simulateAgronomy);
        acresInput.addEventListener("input", simulateAgronomy);
        soilSelect.addEventListener("change", simulateAgronomy);
        irrigationSelect.addEventListener("change", simulateAgronomy);
        fertilizerSelect.addEventListener("change", simulateAgronomy);

        // Run baseline simulation
        simulateAgronomy();
    });
    </script>
</body>
</html>
