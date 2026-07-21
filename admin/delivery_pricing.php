<?php
session_start();
include("../config/db.php");
include("../transport_calculator.php");

$success_msg = "";
$error_msg = "";

// 1. Check if an import was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_rules_btn'])) {
    $json_data = trim($_POST['import_json']);
    $data = json_decode($json_data, true);
    
    if (is_array($data)) {
        mysqli_begin_transaction($conn);
        try {
            // Update core rules
            if (isset($data['rules'])) {
                $r = $data['rules'];
                mysqli_query($conn, "UPDATE delivery_pricing_rules SET 
                    base_fee = '".floatval($r['base_fee'])."',
                    per_km_rate = '".floatval($r['per_km_rate'])."',
                    minimum_fee = '".floatval($r['minimum_fee'])."',
                    maximum_fee = '".floatval($r['maximum_fee'])."',
                    free_delivery_threshold = '".floatval($r['free_delivery_threshold'])."',
                    toll_charges = '".floatval($r['toll_charges'])."',
                    seasonal_charges_flat = '".floatval($r['seasonal_charges_flat'])."',
                    seasonal_charges_multiplier = '".floatval($r['seasonal_charges_multiplier'])."',
                    return_charges_flat = '".floatval($r['return_charges_flat'])."',
                    return_charges_multiplier = '".floatval($r['return_charges_multiplier'])."',
                    weight_slabs_json = '".mysqli_real_escape_string($conn, json_encode($r['weight_slabs']))."',
                    weather_factors_json = '".mysqli_real_escape_string($conn, json_encode($r['weather_factors']))."'
                    WHERE id = 1");
            }
            // Update fuel prices
            if (isset($data['fuels']) && is_array($data['fuels'])) {
                foreach ($data['fuels'] as $f_type => $f) {
                    mysqli_query($conn, "UPDATE fuel_prices SET 
                        base_price = '".floatval($f['base_price'])."',
                        current_price = '".floatval($f['current_price'])."',
                        fuel_adjustment_factor = '".floatval($f['fuel_adjustment_factor'])."'
                        WHERE fuel_type = '".mysqli_real_escape_string($conn, $f_type)."'");
                }
            }
            // Update vehicles
            if (isset($data['vehicles']) && is_array($data['vehicles'])) {
                foreach ($data['vehicles'] as $v_name => $v) {
                    mysqli_query($conn, "UPDATE vehicles SET 
                        multiplier = '".floatval($v['multiplier'])."',
                        max_weight = '".floatval($v['max_weight'])."',
                        cost_per_km = '".floatval($v['cost_per_km'])."',
                        fuel_type = '".mysqli_real_escape_string($conn, $v['fuel_type'])."'
                        WHERE name = '".mysqli_real_escape_string($conn, $v_name)."'");
                }
            }
            // Update zones
            if (isset($data['zones']) && is_array($data['zones'])) {
                foreach ($data['zones'] as $z_name => $z) {
                    mysqli_query($conn, "UPDATE delivery_zones SET 
                        flat_charge = '".floatval($z['flat_charge'])."',
                        multiplier = '".floatval($z['multiplier'])."'
                        WHERE zone_name = '".mysqli_real_escape_string($conn, $z_name)."'");
                }
            }
            // Update roads
            if (isset($data['roads']) && is_array($data['roads'])) {
                foreach ($data['roads'] as $r_name => $rd) {
                    mysqli_query($conn, "UPDATE road_conditions SET 
                        multiplier = '".floatval($rd['multiplier'])."',
                        extra_charge = '".floatval($rd['extra_charge'])."',
                        estimated_speed = '".floatval($rd['estimated_speed'])."'
                        WHERE condition_name = '".mysqli_real_escape_string($conn, $r_name)."'");
                }
            }
            
            // Add audit log
            $desc = mysqli_real_escape_string($conn, "Imported complete logistics configuration backup.");
            mysqli_query($conn, "INSERT INTO delivery_pricing_audit (action_type, description) VALUES ('IMPORT', '$desc')");
            
            mysqli_commit($conn);
            $success_msg = "Rules imported successfully from JSON backup!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "Failed to import rules: " . $e->getMessage();
        }
    } else {
        $error_msg = "Invalid JSON structure. Please paste a valid exported backup.";
    }
}

// 2. Handle core form save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_rules_btn'])) {
    mysqli_begin_transaction($conn);
    try {
        // 2a. Update core rules
        $base_fee = floatval($_POST['base_fee']);
        $per_km_rate = floatval($_POST['per_km_rate']);
        $minimum_fee = floatval($_POST['minimum_fee']);
        $maximum_fee = floatval($_POST['maximum_fee']);
        $free_delivery_threshold = floatval($_POST['free_delivery_threshold']);
        $toll_charges = floatval($_POST['toll_charges']);
        $seasonal_charges_flat = floatval($_POST['seasonal_charges_flat']);
        $seasonal_charges_multiplier = floatval($_POST['seasonal_charges_multiplier']);
        $return_charges_flat = floatval($_POST['return_charges_flat']);
        $return_charges_multiplier = floatval($_POST['return_charges_multiplier']);

        // Weight slabs
        $slab_mins = $_POST['slab_min'];
        $slab_maxs = $_POST['slab_max'];
        $slab_surcharges = $_POST['slab_surcharge'];
        $weight_slabs = [];
        if (is_array($slab_mins)) {
            for ($i = 0; $i < count($slab_mins); $i++) {
                $weight_slabs[] = [
                    'min' => floatval($slab_mins[$i]),
                    'max' => floatval($slab_maxs[$i]),
                    'surcharge' => floatval($slab_surcharges[$i])
                ];
            }
        }
        $weight_slabs_json = mysqli_real_escape_string($conn, json_encode($weight_slabs));

        // Weather multipliers
        $weather_types = ['clear', 'rain', 'flood', 'extreme_heat', 'storm', 'fog'];
        $weather_factors = [];
        foreach ($weather_types as $w) {
            $weather_factors[$w] = [
                'multiplier' => floatval($_POST["weather_{$w}_mult"]),
                'flat' => floatval($_POST["weather_{$w}_flat"])
            ];
        }
        $weather_factors_json = mysqli_real_escape_string($conn, json_encode($weather_factors));

        mysqli_query($conn, "UPDATE delivery_pricing_rules SET 
            base_fee = '$base_fee',
            per_km_rate = '$per_km_rate',
            minimum_fee = '$minimum_fee',
            maximum_fee = '$maximum_fee',
            free_delivery_threshold = '$free_delivery_threshold',
            toll_charges = '$toll_charges',
            seasonal_charges_flat = '$seasonal_charges_flat',
            seasonal_charges_multiplier = '$seasonal_charges_multiplier',
            return_charges_flat = '$return_charges_flat',
            return_charges_multiplier = '$return_charges_multiplier',
            weight_slabs_json = '$weight_slabs_json',
            weather_factors_json = '$weather_factors_json'
            WHERE id = 1");

        // 2b. Update Fuel Prices
        $fuels_q = mysqli_query($conn, "SELECT fuel_type FROM fuel_prices");
        while ($f = mysqli_fetch_assoc($fuels_q)) {
            $ft = $f['fuel_type'];
            $base_p = floatval($_POST["fuel_{$ft}_base"]);
            $curr_p = floatval($_POST["fuel_{$ft}_curr"]);
            $factor = floatval($_POST["fuel_{$ft}_factor"]);
            mysqli_query($conn, "UPDATE fuel_prices SET base_price='$base_p', current_price='$curr_p', fuel_adjustment_factor='$factor' WHERE fuel_type='$ft'");
        }

        // 2c. Update Vehicles
        $veh_q = mysqli_query($conn, "SELECT name FROM vehicles");
        while ($v = mysqli_fetch_assoc($veh_q)) {
            $vn = $v['name'];
            $mult = floatval($_POST["veh_{$vn}_mult"]);
            $max_w = floatval($_POST["veh_{$vn}_maxw"]);
            $cost_km = floatval($_POST["veh_{$vn}_costkm"]);
            $fuel_t = mysqli_real_escape_string($conn, $_POST["veh_{$vn}_fuelt"]);
            mysqli_query($conn, "UPDATE vehicles SET multiplier='$mult', max_weight='$max_w', cost_per_km='$cost_km', fuel_type='$fuel_t' WHERE name='$vn'");
        }

        // 2d. Update Zones
        $zone_q = mysqli_query($conn, "SELECT zone_name FROM delivery_zones");
        while ($z = mysqli_fetch_assoc($zone_q)) {
            $zn = $z['zone_name'];
            $flat = floatval($_POST["zone_{$zn}_flat"]);
            $mult = floatval($_POST["zone_{$zn}_mult"]);
            mysqli_query($conn, "UPDATE delivery_zones SET flat_charge='$flat', multiplier='$mult' WHERE zone_name='$zn'");
        }

        // 2e. Update Road Conditions
        $road_q = mysqli_query($conn, "SELECT condition_name FROM road_conditions");
        while ($r = mysqli_fetch_assoc($road_q)) {
            $rn = $r['condition_name'];
            $mult = floatval($_POST["road_{$rn}_mult"]);
            $flat = floatval($_POST["road_{$rn}_flat"]);
            $speed = floatval($_POST["road_{$rn}_speed"]);
            mysqli_query($conn, "UPDATE road_conditions SET multiplier='$mult', extra_charge='$flat', estimated_speed='$speed' WHERE condition_name='$rn'");
        }

        // Audit Log
        $desc = mysqli_real_escape_string($conn, "Modified delivery pricing parameters across rules, zones, vehicle classes, and fuel matrices.");
        mysqli_query($conn, "INSERT INTO delivery_pricing_audit (action_type, description) VALUES ('UPDATE', '$desc')");

        mysqli_commit($conn);
        $success_msg = "Logistics command center updated successfully!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Error updating pricing tables: " . $e->getMessage();
    }
}

// Fetch current structures for display
$rules_q = mysqli_query($conn, "SELECT * FROM delivery_pricing_rules LIMIT 1");
$rules = mysqli_fetch_assoc($rules_q);

$weight_slabs = json_decode($rules['weight_slabs_json'], true) ?: [];
$weather_factors = json_decode($rules['weather_factors_json'], true) ?: [];

$fuels = [];
$fuels_q = mysqli_query($conn, "SELECT * FROM fuel_prices");
while($f = mysqli_fetch_assoc($fuels_q)) {
    $fuels[$f['fuel_type']] = $f;
}

$vehicles = [];
$veh_q = mysqli_query($conn, "SELECT * FROM vehicles");
while($v = mysqli_fetch_assoc($veh_q)) {
    $vehicles[] = $v;
}

$zones = [];
$zone_q = mysqli_query($conn, "SELECT * FROM delivery_zones");
while($z = mysqli_fetch_assoc($zone_q)) {
    $zones[] = $z;
}

$roads = [];
$road_q = mysqli_query($conn, "SELECT * FROM road_conditions");
while($r = mysqli_fetch_assoc($road_q)) {
    $roads[] = $r;
}

// Fetch Audit logs
$audit_logs = [];
$audit_q = mysqli_query($conn, "SELECT * FROM delivery_pricing_audit ORDER BY id DESC LIMIT 10");
while($a = mysqli_fetch_assoc($audit_q)) {
    $audit_logs[] = $a;
}

// Generate Export Configuration array
$export_config = [
    'rules' => [
        'base_fee' => $rules['base_fee'],
        'per_km_rate' => $rules['per_km_rate'],
        'minimum_fee' => $rules['minimum_fee'],
        'maximum_fee' => $rules['maximum_fee'],
        'free_delivery_threshold' => $rules['free_delivery_threshold'],
        'toll_charges' => $rules['toll_charges'],
        'seasonal_charges_flat' => $rules['seasonal_charges_flat'],
        'seasonal_charges_multiplier' => $rules['seasonal_charges_multiplier'],
        'return_charges_flat' => $rules['return_charges_flat'],
        'return_charges_multiplier' => $rules['return_charges_multiplier'],
        'weight_slabs' => $weight_slabs,
        'weather_factors' => $weather_factors
    ],
    'fuels' => $fuels,
    'vehicles' => [],
    'zones' => [],
    'roads' => []
];
foreach ($vehicles as $v) {
    $export_config['vehicles'][$v['name']] = $v;
}
foreach ($zones as $z) {
    $export_config['zones'][$z['zone_name']] = $z;
}
foreach ($roads as $r) {
    $export_config['roads'][$r['condition_name']] = $r;
}
$export_json = json_encode($export_config, JSON_PRETTY_PRINT);

$complaints_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE status='Pending'");
$pending_complaints = mysqli_fetch_assoc($complaints_query)['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Configuration Hub | AgroNava</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Custom Tabs navigation */
        .tabs-header {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .tab-btn {
            padding: 10px 18px;
            font-weight: 700;
            font-size: 13.5px;
            background: transparent;
            color: var(--text-muted);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .tab-btn.active {
            color: var(--secondary);
            border-bottom: 3px solid var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-card {
            background: white;
            padding: 30px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.01);
            margin-bottom: 28px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
            display: block;
        }

        .input-field {
            width: 100%;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            background: var(--light-bg);
            transition: var(--transition);
        }

        .input-field:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
            box-shadow: 0 0 8px var(--primary-light);
        }

        .alert-banner {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: var(--success-light);
            color: var(--primary-hover);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .slab-row {
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 12px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: var(--radius-sm);
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
            <a href="dashboard.php" class="sidebar-link">
                <i class='ph-duotone ph-squares-four'></i> Platform Overview
            </a>
            <a href="../admin_complaints.php" class="sidebar-link">
                <i class='ph-duotone ph-shield-check'></i> Dispute & Claims
                <?php if ($pending_complaints > 0) { ?>
                    <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 20px; font-size: 11px; margin-left: auto;"><?php echo $pending_complaints; ?></span>
                <?php } ?>
            </a>
            <a href="delivery_pricing.php" class="sidebar-link active">
                <i class='ph-duotone ph-truck'></i> Delivery Pricing
            </a>
            <a href="delivery_analytics.php" class="sidebar-link">
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
                <h2 style="margin: 0; font-size: 20px; font-family: 'Outfit', sans-serif; color: var(--dark);">Logistics Command Center</h2>
                <span style="font-size: 13px; color: var(--text-muted);">Manage real-time dynamic pricing factors, multi-fuel index, and cargo vehicles</span>
            </div>
            <div class="user-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                <span>👑</span> Administrator
            </div>
        </div>

        <div class="admin-content">
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner alert-success">
                    <i class="ph-duotone ph-check-circle" style="font-size: 24px;"></i>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner alert-danger">
                    <i class="ph-duotone ph-x-circle" style="font-size: 24px;"></i>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('core-pricing')">💵 Core Pricing</button>
                <button class="tab-btn" onclick="switchTab('fuel-prices')">⛽ Fuel Index</button>
                <button class="tab-btn" onclick="switchTab('vehicles')">🚚 Vehicles</button>
                <button class="tab-btn" onclick="switchTab('zones-roads')">🗺️ Zones & Roads</button>
                <button class="tab-btn" onclick="switchTab('slabs-weather')">⚖️ Slabs & Weather</button>
                <button class="tab-btn" onclick="switchTab('backup-audit')">📂 Backup & Audit</button>
            </div>

            <form method="POST" action="delivery_pricing.php">
                <input type="hidden" name="save_all_rules_btn" value="1">

                <!-- TAB 1: Core Pricing -->
                <div id="core-pricing" class="tab-content active">
                    <div class="section-card">
                        <h3 class="section-title"><i class="ph-duotone ph-currency-circle-dollar"></i> Base Logistics Rates</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <div class="input-group">
                                <label class="input-label">Base Fee (₹)</label>
                                <input type="number" step="0.01" name="base_fee" class="input-field" value="<?php echo htmlspecialchars($rules['base_fee']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Per KM Rate (₹)</label>
                                <input type="number" step="0.01" name="per_km_rate" class="input-field" value="<?php echo htmlspecialchars($rules['per_km_rate']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Toll Fee flat charge (₹)</label>
                                <input type="number" step="0.01" name="toll_charges" class="input-field" value="<?php echo htmlspecialchars($rules['toll_charges']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Minimum Fee (₹)</label>
                                <input type="number" step="0.01" name="minimum_fee" class="input-field" value="<?php echo htmlspecialchars($rules['minimum_fee']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Maximum Fee Limit (₹)</label>
                                <input type="number" step="0.01" name="maximum_fee" class="input-field" value="<?php echo htmlspecialchars($rules['maximum_fee']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Free Delivery Threshold (₹)</label>
                                <input type="number" step="0.01" name="free_delivery_threshold" class="input-field" value="<?php echo htmlspecialchars($rules['free_delivery_threshold']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <h3 class="section-title"><i class="ph-duotone ph-hourglass"></i> Seasonal & Return Fees</h3>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                            <div class="input-group">
                                <label class="input-label">Seasonal Flat Charge (₹)</label>
                                <input type="number" step="0.01" name="seasonal_charges_flat" class="input-field" value="<?php echo htmlspecialchars($rules['seasonal_charges_flat']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Seasonal Multiplier (x)</label>
                                <input type="number" step="0.01" name="seasonal_charges_multiplier" class="input-field" value="<?php echo htmlspecialchars($rules['seasonal_charges_multiplier']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Return Package Flat (₹)</label>
                                <input type="number" step="0.01" name="return_charges_flat" class="input-field" value="<?php echo htmlspecialchars($rules['return_charges_flat']); ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Return Multiplier Factor (x)</label>
                                <input type="number" step="0.01" name="return_charges_multiplier" class="input-field" value="<?php echo htmlspecialchars($rules['return_charges_multiplier']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Fuel Prices -->
                <div id="fuel-prices" class="tab-content">
                    <div class="section-card">
                        <h3 class="section-title"><i class="ph-duotone ph-gas-pump"></i> Fuel Surcharge Indexing</h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: -15px; margin-bottom: 20px;">
                            Auto-indexes fuel surcharges: <code>(Current Price - Base Price) * Factor * Total Distance</code>
                        </p>
                        
                        <?php foreach($fuels as $ft => $f): ?>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 12px;">
                            <div style="font-weight: 700; text-transform: uppercase; color: var(--secondary);">
                                ⛽ <?php echo htmlspecialchars($ft); ?>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Base Price (₹/L)</label>
                                <input type="number" step="0.01" name="fuel_<?php echo $ft; ?>_base" class="input-field" value="<?php echo htmlspecialchars($f['base_price']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Current Price (₹/L)</label>
                                <input type="number" step="0.01" name="fuel_<?php echo $ft; ?>_curr" class="input-field" value="<?php echo htmlspecialchars($f['current_price']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Adjustment Factor (x)</label>
                                <input type="number" step="0.0001" name="fuel_<?php echo $ft; ?>_factor" class="input-field" value="<?php echo htmlspecialchars($f['fuel_adjustment_factor']); ?>" required>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB 3: Vehicles -->
                <div id="vehicles" class="tab-content">
                    <div class="section-card">
                        <h3 class="section-title"><i class="ph-duotone ph-moped"></i> Active Vehicle System</h3>
                        <?php foreach($vehicles as $v): ?>
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 12px;">
                            <div style="font-weight: 700; color: var(--dark); font-size: 13.5px; grid-column: span 1;">
                                <?php echo htmlspecialchars($v['display_name']); ?>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Multiplier (x)</label>
                                <input type="number" step="0.01" name="veh_<?php echo $v['name']; ?>_mult" class="input-field" value="<?php echo htmlspecialchars($v['multiplier']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Max Payload Capacity (kg)</label>
                                <input type="number" step="0.01" name="veh_<?php echo $v['name']; ?>_maxw" class="input-field" value="<?php echo htmlspecialchars($v['max_weight']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Transit Cost / KM (₹)</label>
                                <input type="number" step="0.01" name="veh_<?php echo $v['name']; ?>_costkm" class="input-field" value="<?php echo htmlspecialchars($v['cost_per_km']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Linked Fuel Type</label>
                                <select name="veh_<?php echo $v['name']; ?>_fuelt" class="input-field" style="background-color: white;">
                                    <option value="petrol" <?php echo ($v['fuel_type'] === 'petrol') ? 'selected' : ''; ?>>Petrol</option>
                                    <option value="diesel" <?php echo ($v['fuel_type'] === 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                    <option value="cng" <?php echo ($v['fuel_type'] === 'cng') ? 'selected' : ''; ?>>CNG</option>
                                    <option value="electric" <?php echo ($v['fuel_type'] === 'electric') ? 'selected' : ''; ?>>Electric</option>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB 4: Zones & Roads -->
                <div id="zones-roads" class="tab-content">
                    <div class="section-card">
                        <h3 class="section-title"><i class="ph-duotone ph-map-pin"></i> Delivery Zone Surcharges</h3>
                        <?php foreach($zones as $z): ?>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 10px;">
                            <div style="font-weight: 700; color: var(--secondary);">
                                📍 <?php echo htmlspecialchars($z['display_name']); ?>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Flat Charge (₹)</label>
                                <input type="number" step="0.01" name="zone_<?php echo $z['zone_name']; ?>_flat" class="input-field" value="<?php echo htmlspecialchars($z['flat_charge']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Zone Multiplier (x)</label>
                                <input type="number" step="0.01" name="zone_<?php echo $z['zone_name']; ?>_mult" class="input-field" value="<?php echo htmlspecialchars($z['multiplier']); ?>" required>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-card">
                        <h3 class="section-title"><i class="ph-duotone ph-road-horizon"></i> Road Conditions speed indexing</h3>
                        <?php foreach($roads as $r): ?>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 10px;">
                            <div style="font-weight: 700; color: var(--dark); font-size: 13.5px;">
                                <?php echo htmlspecialchars($r['display_name']); ?>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Road Multiplier (x)</label>
                                <input type="number" step="0.01" name="road_<?php echo $r['condition_name']; ?>_mult" class="input-field" value="<?php echo htmlspecialchars($r['multiplier']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Surcharge (₹)</label>
                                <input type="number" step="0.01" name="road_<?php echo $r['condition_name']; ?>_flat" class="input-field" value="<?php echo htmlspecialchars($r['extra_charge']); ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom:0;">
                                <label class="input-label">Avg Speed (km/h)</label>
                                <input type="number" step="0.1" name="road_<?php echo $r['condition_name']; ?>_speed" class="input-field" value="<?php echo htmlspecialchars($r['estimated_speed']); ?>" required>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB 5: Slabs & Weather -->
                <div id="slabs-weather" class="tab-content">
                    <div class="section-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 class="section-title" style="margin: 0; border: none; padding: 0;"><i class="ph-duotone ph-barbell"></i> Weight Slab surcharges</h3>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addSlabRow()"><i class="ph-bold ph-plus"></i> Add Slab</button>
                        </div>
                        <div id="slabs-container">
                            <?php foreach($weight_slabs as $index => $slab): ?>
                                <div class="slab-row" id="slab-row-<?php echo $index; ?>">
                                    <div style="flex: 1;">
                                        <label class="input-label">Min Weight (kg)</label>
                                        <input type="number" step="0.01" name="slab_min[]" class="input-field" value="<?php echo htmlspecialchars($slab['min']); ?>" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <label class="input-label">Max Weight (kg)</label>
                                        <input type="number" step="0.01" name="slab_max[]" class="input-field" value="<?php echo htmlspecialchars($slab['max']); ?>" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <label class="input-label">Flat Surcharge (₹)</label>
                                        <input type="number" step="0.01" name="slab_surcharge[]" class="input-field" value="<?php echo htmlspecialchars($slab['surcharge']); ?>" required>
                                    </div>
                                    <div style="margin-top: 22px;">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeSlabRow(<?php echo $index; ?>)"><i class="ph-bold ph-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="section-card">
                        <h3 class="section-title"><i class="ph-duotone ph-cloud-rain"></i> Weather Ready Placeholders</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <?php foreach($weather_factors as $w => $fac): ?>
                            <div style="border: 1px solid var(--border); padding: 14px; border-radius: var(--radius-sm);">
                                <span style="font-weight: 700; text-transform: capitalize; font-size:14px; display:block; margin-bottom:8px;"><?php echo htmlspecialchars($w); ?> Factor</span>
                                <div class="input-group">
                                    <label class="input-label">Multiplier (x)</label>
                                    <input type="number" step="0.01" name="weather_<?php echo $w; ?>_mult" class="input-field" value="<?php echo htmlspecialchars($fac['multiplier']); ?>" required>
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Flat Fee (₹)</label>
                                    <input type="number" step="0.01" name="weather_<?php echo $w; ?>_flat" class="input-field" value="<?php echo htmlspecialchars($fac['flat']); ?>" required>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Action Submit -->
                <div style="display: flex; gap: 16px; justify-content: flex-end; margin-bottom: 60px;">
                    <a href="dashboard.php" class="btn btn-secondary">Discard Changes</a>
                    <button type="submit" class="btn btn-primary">Save Pricing Configuration Center</button>
                </div>
            </form>

            <!-- TAB 6: Backup & Audit -->
            <div id="backup-audit" class="tab-content">
                <div class="section-card">
                    <h3 class="section-title"><i class="ph-duotone ph-database"></i> Backup & Restore JSON pricing rules</h3>
                    <p style="font-size: 13px; color: var(--text-muted); margin-top: -15px; margin-bottom: 20px;">
                        Copy the configuration payload below to export backup configs, or paste a previously saved JSON payload and submit Import to update the entire logistics engine rules instantly.
                    </p>
                    <form method="POST" action="delivery_pricing.php">
                        <div class="input-group">
                            <label class="input-label">JSON Rules Configuration Payload</label>
                            <textarea name="import_json" class="input-field" style="font-family: monospace; font-size: 12px; height: 250px; background-color: #0f172a; color: #38bdf8; border: none; padding: 16px; resize: vertical;"><?php echo htmlspecialchars($export_json); ?></textarea>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                            <span style="font-size:12px; color: var(--text-muted); font-weight:600;"><i class="ph-duotone ph-warning"></i> Warning: Importing will overwrite all active vehicle/fuel/zone definitions.</span>
                            <button type="submit" name="import_rules_btn" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, var(--secondary), #0d9488); border: none;">⬇️ Import & Override Configs</button>
                        </div>
                    </form>
                </div>

                <div class="section-card">
                    <h3 class="section-title"><i class="ph-duotone ph-clock"></i> Command Center Audit Logs</h3>
                    <div style="overflow-x: auto;">
                        <table class="ledger-table" style="width:100%; min-width:600px;">
                            <thead>
                                <tr>
                                    <th>Audit ID</th>
                                    <th>Admin ID</th>
                                    <th>Action Type</th>
                                    <th>Change Description</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($audit_logs)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted);">No configuration changes recorded in the audit ledger.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td>#<?php echo $log['id']; ?></td>
                                        <td>System / Root</td>
                                        <td><span style="background: rgba(79, 70, 229, 0.1); color: #4f46e5; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight:700;"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td><?php echo date("M j Y, g:i A", strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        let slabIndex = <?php echo count($weight_slabs); ?>;

        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            
            // Find button trigger and add active
            const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
            if (btn) btn.classList.add('active');
        }

        function addSlabRow() {
            const container = document.getElementById('slabs-container');
            const row = document.createElement('div');
            row.className = 'slab-row';
            row.id = `slab-row-${slabIndex}`;
            row.innerHTML = `
                <div style="flex: 1;">
                    <label class="input-label">Min Weight (kg)</label>
                    <input type="number" step="0.01" name="slab_min[]" class="input-field" value="0" required>
                </div>
                <div style="flex: 1;">
                    <label class="input-label">Max Weight (kg)</label>
                    <input type="number" step="0.01" name="slab_max[]" class="input-field" value="10" required>
                </div>
                <div style="flex: 1;">
                    <label class="input-label">Flat Surcharge (₹)</label>
                    <input type="number" step="0.01" name="slab_surcharge[]" class="input-field" value="0" required>
                </div>
                <div style="margin-top: 22px;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSlabRow(${slabIndex})"><i class="ph-bold ph-trash"></i></button>
                </div>
            `;
            container.appendChild(row);
            slabIndex++;
        }

        function removeSlabRow(id) {
            const row = document.getElementById(`slab-row-${id}`);
            if (row) {
                row.remove();
            }
        }
    </script>
</body>
</html>
