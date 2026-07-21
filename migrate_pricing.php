<?php
include("config/db.php");

echo "<h3><i class='ph-duotone ph-gear'></i> Establishing Dynamic Pricing Schema</h3>";

// 1. Create delivery_pricing_rules table
$pricing_rules_sql = "CREATE TABLE IF NOT EXISTS `delivery_pricing_rules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `base_fee` decimal(10,2) NOT NULL DEFAULT 30.00,
    `per_km_rate` decimal(10,2) NOT NULL DEFAULT 8.00,
    `minimum_fee` decimal(10,2) NOT NULL DEFAULT 50.00,
    `free_delivery_threshold` decimal(10,2) NOT NULL DEFAULT 1000.00,
    `base_fuel_price` decimal(10,2) NOT NULL DEFAULT 95.00,
    `current_fuel_price` decimal(10,2) NOT NULL DEFAULT 100.00,
    `fuel_adjustment_factor` decimal(10,4) NOT NULL DEFAULT 0.0500,
    `rural_surcharge_flat` decimal(10,2) NOT NULL DEFAULT 40.00,
    `rural_surcharge_multiplier` decimal(5,2) NOT NULL DEFAULT 1.15,
    `weight_slabs_json` text NOT NULL,
    `vehicle_multipliers_json` text NOT NULL,
    `express_charges_json` text NOT NULL,
    `road_conditions_multipliers_json` text NOT NULL,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $pricing_rules_sql)) {
    echo "✔ `delivery_pricing_rules` table established successfully!<br>";
} else {
    echo "❌ Failed to create `delivery_pricing_rules` table: " . mysqli_error($conn) . "<br>";
}

// 2. Insert default pricing rule row if empty
$check_empty = mysqli_query($conn, "SELECT id FROM delivery_pricing_rules LIMIT 1");
if ($check_empty && mysqli_num_rows($check_empty) == 0) {
    $weight_slabs = json_encode([
        ["min" => 0, "max" => 10, "surcharge" => 0],
        ["min" => 10, "max" => 50, "surcharge" => 20],
        ["min" => 50, "max" => 200, "surcharge" => 50],
        ["min" => 200, "max" => 999999, "surcharge" => 120]
    ]);
    
    $vehicle_multipliers = json_encode([
        "bike" => 1.0,
        "auto" => 1.4,
        "mini_truck" => 2.0,
        "heavy_truck" => 3.5
    ]);
    
    $express_charges = json_encode([
        "standard" => ["multiplier" => 1.0, "flat" => 0],
        "express" => ["multiplier" => 1.25, "flat" => 45],
        "urgent" => ["multiplier" => 1.5, "flat" => 90]
    ]);
    
    $road_conditions = json_encode([
        "good" => 1.0,
        "rough" => 1.25,
        "muddy" => 1.5
    ]);

    $insert_rules_sql = "INSERT INTO delivery_pricing_rules (
        base_fee, per_km_rate, minimum_fee, free_delivery_threshold, 
        base_fuel_price, current_fuel_price, fuel_adjustment_factor, 
        rural_surcharge_flat, rural_surcharge_multiplier, 
        weight_slabs_json, vehicle_multipliers_json, 
        express_charges_json, road_conditions_multipliers_json
    ) VALUES (
        30.00, 8.00, 50.00, 1000.00, 
        95.00, 100.00, 0.0500, 
        40.00, 1.15, 
        '$weight_slabs', '$vehicle_multipliers', 
        '$express_charges', '$road_conditions'
    )";
    
    if (mysqli_query($conn, $insert_rules_sql)) {
        echo "✔ Baseline pricing rules inserted successfully!<br>";
    } else {
        echo "❌ Failed to insert baseline pricing rules: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "✔ Baseline pricing rules already configured.<br>";
}

// 3. Alter orders table to include delivery parameters
$columns_to_add = [
    'delivery_details' => "TEXT DEFAULT NULL",
    'vehicle_type' => "varchar(50) DEFAULT 'bike'",
    'delivery_priority' => "varchar(50) DEFAULT 'standard'",
    'road_condition' => "varchar(50) DEFAULT 'good'",
    'location_type' => "varchar(50) DEFAULT 'urban'"
];

foreach ($columns_to_add as $col_name => $col_def) {
    // Check if column already exists
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE '$col_name'");
    if (mysqli_num_rows($col_check) == 0) {
        $alter_sql = "ALTER TABLE `orders` ADD COLUMN `$col_name` $col_def";
        if (mysqli_query($conn, $alter_sql)) {
            echo "✔ Column `$col_name` added to `orders` table.<br>";
        } else {
            echo "❌ Failed to add column `$col_name` to `orders`: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "✔ Column `$col_name` already exists in `orders` table.<br>";
    }
}

echo "<h3>Migration completed!</h3>";
?>
