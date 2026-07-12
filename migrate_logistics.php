<?php
include("config/db.php");

echo "<h3><i class='ph-duotone ph-truck'></i> Establishing Logistics Tracking Ledger</h3>";

$tracking_sql = "CREATE TABLE IF NOT EXISTS `order_tracking` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `tracking_status` varchar(100) NOT NULL,
    `location` varchar(255) DEFAULT NULL,
    `updated_by_role` varchar(50) DEFAULT NULL,
    `updated_by_id` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $tracking_sql)) {
    echo "<i class='ph-duotone ph-check-circle'></i> `order_tracking` table established successfully!<br>";
} else {
    echo "<i class='ph-duotone ph-x-circle'></i> Failed to create `order_tracking` table: " . mysqli_error($conn) . "<br>";
}

// Backfill existing orders with an initial tracking state so they don't break the UI
$orders_sql = "SELECT id, tracking_status FROM orders WHERE tracking_status IS NOT NULL AND status != 'pending'";
$res = mysqli_query($conn, $orders_sql);
$count = 0;
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        $order_id = $row['id'];
        $status = mysqli_real_escape_string($conn, $row['tracking_status']);
        
        // Check if tracking already exists for this order
        $check = mysqli_query($conn, "SELECT id FROM order_tracking WHERE order_id = '$order_id'");
        if (mysqli_num_rows($check) == 0) {
            $insert = "INSERT INTO order_tracking (order_id, tracking_status, location, updated_by_role) VALUES ('$order_id', '$status', 'Central DB Backfill', 'system')";
            mysqli_query($conn, $insert);
            $count++;
        }
    }
}
echo "Backfilled $count existing orders into the tracking ledger.<br>";

?>
