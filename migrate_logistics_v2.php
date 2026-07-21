<?php
include("config/db.php");

echo "<h3><i class='ph-duotone ph-gear'></i> Establishing Production-Grade Logistics Database Schema</h3>";

// 1. Drop existing delivery_pricing_rules to ensure we match new columns exactly
mysqli_query($conn, "DROP TABLE IF EXISTS `delivery_pricing_rules`");

// 2. Create delivery_pricing_rules
$rules_sql = "CREATE TABLE `delivery_pricing_rules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `base_fee` decimal(10,2) NOT NULL DEFAULT 30.00,
    `per_km_rate` decimal(10,2) NOT NULL DEFAULT 8.00,
    `minimum_fee` decimal(10,2) NOT NULL DEFAULT 50.00,
    `maximum_fee` decimal(10,2) NOT NULL DEFAULT 1500.00,
    `free_delivery_threshold` decimal(10,2) NOT NULL DEFAULT 1000.00,
    `toll_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
    `seasonal_charges_flat` decimal(10,2) NOT NULL DEFAULT 0.00,
    `seasonal_charges_multiplier` decimal(5,2) NOT NULL DEFAULT 1.00,
    `return_charges_flat` decimal(10,2) NOT NULL DEFAULT 50.00,
    `return_charges_multiplier` decimal(5,2) NOT NULL DEFAULT 0.50,
    `weight_slabs_json` text NOT NULL,
    `weather_factors_json` text NOT NULL,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $rules_sql)) {
    echo "✔ `delivery_pricing_rules` table created.<br>";
} else {
    echo "❌ Failed to create `delivery_pricing_rules`: " . mysqli_error($conn) . "<br>";
}

// Seed initial rules
$weight_slabs = json_encode([
    ["min" => 0, "max" => 5, "surcharge" => 0],
    ["min" => 5, "max" => 20, "surcharge" => 20],
    ["min" => 20, "max" => 50, "surcharge" => 50],
    ["min" => 50, "max" => 100, "surcharge" => 100],
    ["min" => 100, "max" => 999999, "surcharge" => 250]
]);

$weather_factors = json_encode([
    "clear" => ["multiplier" => 1.0, "flat" => 0],
    "rain" => ["multiplier" => 1.15, "flat" => 30],
    "flood" => ["multiplier" => 1.5, "flat" => 100],
    "extreme_heat" => ["multiplier" => 1.05, "flat" => 0],
    "storm" => ["multiplier" => 1.3, "flat" => 60],
    "fog" => ["multiplier" => 1.2, "flat" => 40]
]);

$insert_rules = "INSERT INTO `delivery_pricing_rules` (
    base_fee, per_km_rate, minimum_fee, maximum_fee, free_delivery_threshold, 
    toll_charges, seasonal_charges_flat, seasonal_charges_multiplier, 
    return_charges_flat, return_charges_multiplier, 
    weight_slabs_json, weather_factors_json
) VALUES (
    30.00, 8.00, 50.00, 1500.00, 1000.00, 
    0.00, 0.00, 1.00, 
    50.00, 0.50, 
    '$weight_slabs', '$weather_factors'
)";
mysqli_query($conn, $insert_rules);

// 3. Create vehicles table
mysqli_query($conn, "DROP TABLE IF EXISTS `vehicles`");
$vehicles_sql = "CREATE TABLE `vehicles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL UNIQUE,
    `display_name` varchar(100) NOT NULL,
    `multiplier` decimal(5,2) NOT NULL DEFAULT 1.00,
    `max_weight` decimal(10,2) NOT NULL DEFAULT 20.00,
    `cost_per_km` decimal(10,2) NOT NULL DEFAULT 0.00,
    `fuel_type` varchar(20) NOT NULL DEFAULT 'petrol',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

mysqli_query($conn, $vehicles_sql);
echo "✔ `vehicles` table created.<br>";

// Seed vehicles
$seed_vehicles = [
    ["bike", "🛵 2-Wheeler (Motorcycle / Scooter)", 1.00, 20.00, 1.50, "petrol"],
    ["auto", "🛺 3-Wheeler (Auto Rickshaw)", 1.30, 150.00, 3.00, "cng"],
    ["pickup_van", "🛻 Pickup Van (Boloero Camper)", 1.70, 750.00, 6.00, "diesel"],
    ["mini_truck", "🚚 Mini Truck (Tata Ace / Chota Hathi)", 2.00, 1500.00, 8.00, "diesel"],
    ["heavy_truck", "🚛 Large Commercial Truck", 3.20, 10000.00, 15.00, "diesel"],
    ["cold_storage_truck", "❄️ Cold Storage Refrigerated Truck", 3.80, 5000.00, 20.00, "diesel"],
    ["electric_vehicle", "⚡ Electric Cargo Vehicle", 0.90, 100.00, 0.80, "electric"]
];
foreach ($seed_vehicles as $v) {
    mysqli_query($conn, "INSERT INTO `vehicles` (name, display_name, multiplier, max_weight, cost_per_km, fuel_type) 
                         VALUES ('{$v[0]}', '{$v[1]}', '{$v[2]}', '{$v[3]}', '{$v[4]}', '{$v[5]}')");
}

// 4. Create fuel_prices table
mysqli_query($conn, "DROP TABLE IF EXISTS `fuel_prices`");
$fuel_sql = "CREATE TABLE `fuel_prices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `fuel_type` varchar(20) NOT NULL UNIQUE,
    `base_price` decimal(10,2) NOT NULL,
    `current_price` decimal(10,2) NOT NULL,
    `fuel_adjustment_factor` decimal(10,4) NOT NULL DEFAULT 0.0500,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

mysqli_query($conn, $fuel_sql);
echo "✔ `fuel_prices` table created.<br>";

// Seed fuel prices
$seed_fuel = [
    ["petrol", 96.00, 101.50, 0.0300],
    ["diesel", 89.00, 94.00, 0.0450],
    ["cng", 75.00, 82.00, 0.0200],
    ["electric", 8.00, 8.00, 0.0050]
];
foreach ($seed_fuel as $f) {
    mysqli_query($conn, "INSERT INTO `fuel_prices` (fuel_type, base_price, current_price, fuel_adjustment_factor) 
                         VALUES ('{$f[0]}', '{$f[1]}', '{$f[2]}', '{$f[3]}')");
}

// 5. Create delivery_zones table
mysqli_query($conn, "DROP TABLE IF EXISTS `delivery_zones`");
$zones_sql = "CREATE TABLE `delivery_zones` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `zone_name` varchar(50) NOT NULL UNIQUE,
    `display_name` varchar(100) NOT NULL,
    `flat_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
    `multiplier` decimal(5,2) NOT NULL DEFAULT 1.00,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

mysqli_query($conn, $zones_sql);
echo "✔ `delivery_zones` table created.<br>";

// Seed zones
$seed_zones = [
    ["urban", "Urban Area (City)", 0.00, 1.00],
    ["semi_urban", "Semi Urban (Suburbs)", 20.00, 1.08],
    ["rural", "Rural Area (Village Surcharge)", 40.00, 1.15],
    ["remote_village", "Remote Village (Outpost Surcharge)", 75.00, 1.25],
    ["hill_area", "Hill Area (Mountainous Surcharge)", 100.00, 1.40],
    ["industrial_area", "Industrial Area / SEZ", 30.00, 1.10]
];
foreach ($seed_zones as $z) {
    mysqli_query($conn, "INSERT INTO `delivery_zones` (zone_name, display_name, flat_charge, multiplier) 
                         VALUES ('{$z[0]}', '{$z[1]}', '{$z[2]}', '{$z[3]}')");
}

// 6. Create road_conditions table
mysqli_query($conn, "DROP TABLE IF EXISTS `road_conditions`");
$roads_sql = "CREATE TABLE `road_conditions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `condition_name` varchar(50) NOT NULL UNIQUE,
    `display_name` varchar(100) NOT NULL,
    `multiplier` decimal(5,2) NOT NULL DEFAULT 1.00,
    `extra_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
    `estimated_speed` decimal(5,2) NOT NULL DEFAULT 40.00,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

mysqli_query($conn, $roads_sql);
echo "✔ `road_conditions` table created.<br>";

// Seed roads
$seed_roads = [
    ["highway", "🛣️ Smooth Highway / Expressway", 1.00, 0.00, 75.00],
    ["city_road", "🏙️ Standard City Road", 1.08, 10.00, 35.00],
    ["village_road", "🚜 Narrow Village Road", 1.15, 25.00, 25.00],
    ["rough_road", "🚧 Potholes & Construction zones", 1.25, 40.00, 18.00],
    ["muddy_road", "⛈️ Rainy / Muddy Trail", 1.40, 60.00, 12.00],
    ["hill_road", "⛰️ Hill Road / Hairpin bends", 1.45, 80.00, 20.00]
];
foreach ($seed_roads as $r) {
    mysqli_query($conn, "INSERT INTO `road_conditions` (condition_name, display_name, multiplier, extra_charge, estimated_speed) 
                         VALUES ('{$r[0]}', '{$r[1]}', '{$r[2]}', '{$r[3]}', '{$r[4]}')");
}

// 7. Create collection_centers
mysqli_query($conn, "DROP TABLE IF EXISTS `collection_centers`");
$cc_sql = "CREATE TABLE `collection_centers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `address` varchar(255) NOT NULL,
    `latitude` decimal(10,6) NOT NULL,
    `longitude` decimal(10,6) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $cc_sql);

// Seed collection centers
mysqli_query($conn, "INSERT INTO `collection_centers` (name, address, latitude, longitude) VALUES 
('Mohali Agribusiness Center', 'Phase 8B, Industrial Area, Mohali', 30.7046, 76.7179),
('Ludhiana Grain Hub', 'Grain Market Road, Ludhiana', 30.9010, 75.8573),
('Jalandhar Vegetable Exchange', 'New Subzi Mandi, Jalandhar', 31.3260, 75.5762)");
echo "✔ `collection_centers` table created.<br>";

// 8. Create warehouses
mysqli_query($conn, "DROP TABLE IF EXISTS `warehouses`");
$wh_sql = "CREATE TABLE `warehouses` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `address` varchar(255) NOT NULL,
    `latitude` decimal(10,6) NOT NULL,
    `longitude` decimal(10,6) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $wh_sql);

// Seed warehouses
mysqli_query($conn, "INSERT INTO `warehouses` (name, address, latitude, longitude) VALUES 
('Chandigarh Central Storage', 'Industrial Area Phase 1, Chandigarh', 30.7333, 76.7794),
('Ludhiana Regional Depot', 'GT Road, Ludhiana Bypass', 30.9200, 75.8000)");
echo "✔ `warehouses` table created.<br>";

// 9. Create return_logistics table
mysqli_query($conn, "DROP TABLE IF EXISTS `return_logistics`");
$ret_sql = "CREATE TABLE `return_logistics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `pickup_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
    `warehouse_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
    `farmer_return_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
    `return_transportation_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
    `total_return_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
    `status` varchar(50) NOT NULL DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $ret_sql);
echo "✔ `return_logistics` table created.<br>";

// 10. Create delivery_pricing_audit table
mysqli_query($conn, "DROP TABLE IF EXISTS `delivery_pricing_audit`");
$audit_sql = "CREATE TABLE `delivery_pricing_audit` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) DEFAULT NULL,
    `action_type` varchar(50) NOT NULL,
    `description` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $audit_sql);
echo "✔ `delivery_pricing_audit` table created.<br>";

// 11. Add new columns to orders table
$orders_columns = [
    'delivery_details' => "TEXT DEFAULT NULL",
    'delivery_distance' => "decimal(10,2) DEFAULT 0.00",
    'delivery_zone' => "varchar(50) DEFAULT 'urban'",
    'vehicle_type' => "varchar(50) DEFAULT 'bike'",
    'delivery_priority' => "varchar(50) DEFAULT 'standard'",
    'road_condition' => "varchar(50) DEFAULT 'good'",
    'fuel_adjustment' => "decimal(10,2) DEFAULT 0.00",
    'estimated_delivery_time' => "varchar(100) DEFAULT NULL",
    'estimated_arrival' => "datetime DEFAULT NULL",
    'delivery_route' => "TEXT DEFAULT NULL",
    'warehouse_id' => "int(11) DEFAULT NULL",
    'collection_center_id' => "int(11) DEFAULT NULL"
];

foreach ($orders_columns as $col_name => $col_def) {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE '$col_name'");
    if (mysqli_num_rows($col_check) == 0) {
        $alter_sql = "ALTER TABLE `orders` ADD COLUMN `$col_name` $col_def";
        if (mysqli_query($conn, $alter_sql)) {
            echo "✔ Column `$col_name` added to `orders`.<br>";
        } else {
            echo "❌ Failed to add column `$col_name` to `orders`: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "✔ Column `$col_name` already exists in `orders` (re-initialized definition).<br>";
    }
}

echo "<h3>Migration completed successfully! All logistics tables primed.</h3>";
?>
