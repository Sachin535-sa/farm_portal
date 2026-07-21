<?php
session_start();
include("../config/db.php");

// Session validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer") {
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);

    // Begin database transaction
    mysqli_begin_transaction($conn);
    try {
        // Fetch order details to ensure it belongs to this buyer and has valid status (shipped or delivered)
        $order_q = mysqli_query($conn, "SELECT o.*, c.farmer_id, c.crop_name 
                                       FROM orders o 
                                       JOIN crops c ON o.crop_id = c.id 
                                       WHERE o.id = '$order_id' AND o.buyer_id = '$buyer_id' 
                                       AND o.status IN ('shipped', 'delivered') 
                                       FOR UPDATE");
        
        if ($order_q && mysqli_num_rows($order_q) > 0) {
            $order = mysqli_fetch_assoc($order_q);
            $farmer_id = $order['farmer_id'];
            $crop_name = $order['crop_name'];
            $transport_cost = floatval($order['transport_cost']);

            // Fetch return pricing configuration rules
            $rules_q = mysqli_query($conn, "SELECT * FROM delivery_pricing_rules LIMIT 1");
            $rules = mysqli_fetch_assoc($rules_q);
            
            $flat_ret = $rules ? floatval($rules['return_charges_flat']) : 50.00;
            $mult_ret = $rules ? floatval($rules['return_charges_multiplier']) : 0.50;

            // Formulate returns breakdown
            $pickup_cost = $flat_ret * 0.40; // 40% of flat return surcharge covers driver pickup
            $warehouse_cost = 30.00; // Flat warehouse receipt handling
            $farmer_return_cost = $flat_ret * 0.60; // 60% farmer admin handling fee
            $return_transport_charges = $transport_cost * $mult_ret; // discounted return distance trip
            
            $total_return_cost = $pickup_cost + $warehouse_cost + $farmer_return_cost + $return_transport_charges;

            // 1. Record return logistics record
            $ins_sql = "INSERT INTO return_logistics (
                order_id, pickup_cost, warehouse_cost, farmer_return_cost, return_transportation_charges, total_return_cost, status
            ) VALUES (
                '$order_id', '$pickup_cost', '$warehouse_cost', '$farmer_return_cost', '$return_transport_charges', '$total_return_cost', 'returned'
            )";
            mysqli_query($conn, $ins_sql);

            // 2. Update order status to rejected/returned and record node tracking
            mysqli_query($conn, "UPDATE orders SET status = 'returned', tracking_status = 'Returned to Grower' WHERE id = '$order_id'");
            
            // Add tracking ledger node
            mysqli_query($conn, "INSERT INTO order_tracking (order_id, tracking_status, location, updated_by_role, updated_by_id) 
                                 VALUES ('$order_id', 'Returned to Grower', 'Fulfillment Center (Return Cargo Pipeline)', 'buyer', '$buyer_id')");

            // 3. Notify farmer
            $notif_msg = mysqli_real_escape_string($conn, "↩️ Delivery Rejected: Buyer has rejected Order #$order_id ($crop_name). Cargo return pipeline initiated. Associated return logistics cost: ₹" . number_format($total_return_cost, 2));
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$notif_msg')");

            mysqli_commit($conn);
            header("Location: my_orders.php?rejected_success=1");
            exit();
        } else {
            mysqli_rollback($conn);
            header("Location: my_orders.php?err=cannot_reject");
            exit();
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: my_orders.php?err=system_error");
        exit();
    }
} else {
    header("Location: my_orders.php");
    exit();
}
?>
