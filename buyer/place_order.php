<?php
session_start();
include("../config/db.php");
include("../transport_calculator.php");

// Session check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer"){
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

if(isset($_POST['place_order'])) {
    
    $crop_id = mysqli_real_escape_string($conn, $_POST['crop_id']);
    $qty_to_order = (int)$_POST['quantity'];
    
    if($qty_to_order <= 0) {
        header("Location: marketplace.php?err=invalid_quantity");
        exit();
    }
    
    // Begin transaction check
    mysqli_begin_transaction($conn);
    
    try {
        // Fetch current crop details for validation (locking row)
        $sql_crop = "SELECT * FROM crops WHERE id = '$crop_id' FOR UPDATE";
        $res_crop = mysqli_query($conn, $sql_crop);
        
        if($res_crop && mysqli_num_rows($res_crop) > 0) {
            $crop = mysqli_fetch_assoc($res_crop);
            $available_qty = (int)$crop['quantity'];
            
            // Check inventory capacity
            if($available_qty >= $qty_to_order) {
                // 1. Calculate new remaining stock
                $new_qty = $available_qty - $qty_to_order;
                
                // 2. Update crop inventory
                $update_sql = "UPDATE crops SET quantity = '$new_qty' WHERE id = '$crop_id'";
                mysqli_query($conn, $update_sql);
                
                // 3. Retrieve accepted bargain price if it exists, otherwise use standard listing price
                $farmer_id = $crop['farmer_id'];
                $bargain_sql = "SELECT proposed_price FROM bargains WHERE buyer_id = '$buyer_id' AND crop_id = '$crop_id' AND status = 'accepted' LIMIT 1";
                $bargain_res = mysqli_query($conn, $bargain_sql);
                
                $final_unit_price = (int)$crop['price'];
                if ($bargain_res && mysqli_num_rows($bargain_res) > 0) {
                    $bargain_row = mysqli_fetch_assoc($bargain_res);
                    $final_unit_price = (int)$bargain_row['proposed_price'];
                }
                
                // AgriDirect: Calculate Transport Cost and Packaging Surcharges
                $transportCalc = new TransportCalculator();
                $distance_km = rand(5, 50); // Simulating distance for demo
                $weight_kg = $qty_to_order;
                $transport_cost = $transportCalc->calculateAdvanced($distance_km, $weight_kg, $crop['crop_name']);
                
                $pkg_details = $transportCalc->getPackagingDetails($crop['crop_name']);
                $package_type = mysqli_real_escape_string($conn, $pkg_details['type']);
                
                // Read payment method selection
                $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : 'COD';
                $order_status = ($payment_method === 'UPI') ? 'pending_payment' : 'pending';
                
                // AgriDirect: Generate Delivery OTP & QR Hash
                $delivery_otp = rand(100000, 999999);
                $qr_data = $buyer_id . "-" . $crop_id . "-" . time() . "-" . rand(100,999);
                $qr_code_hash = md5($qr_data);
                
                // 4. Create entry in orders table with historically preserved price and new delivery features
                $insert_sql = "INSERT INTO orders (buyer_id, crop_id, quantity, price, status, transport_cost, delivery_otp, tracking_status, qr_code_hash, distance_km, weight_kg, package_type) 
                               VALUES ('$buyer_id', '$crop_id', '$qty_to_order', '$final_unit_price', '$order_status', '$transport_cost', '$delivery_otp', 'Preparing', '$qr_code_hash', '$distance_km', '$weight_kg', '$package_type')";
                mysqli_query($conn, $insert_sql);
                $order_id = mysqli_insert_id($conn);
                
                // 5. Send Real-Time Notifications (Skip billing notification if payment pending)
                $crop_name_clean = mysqli_real_escape_string($conn, $crop['crop_name']);
                
                if ($payment_method !== 'UPI') {
                    $buyer_msg = "🛍️ Purchase Successful: You have successfully purchased " . $qty_to_order . " kg of " . $crop['crop_name'] . "!";
                    $buyer_msg_clean = mysqli_real_escape_string($conn, $buyer_msg);
                    mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$buyer_msg_clean')");
                    
                    $otp_msg = "🔐 Delivery OTP for Order #".$order_id.": " . $delivery_otp . " (Keep this safe!)";
                    mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$otp_msg')");
                    
                    $farmer_msg = "🌾 Harvest Sold: Buyer has purchased " . $qty_to_order . " kg of your listed " . $crop['crop_name'] . "!";
                    $farmer_msg_clean = mysqli_real_escape_string($conn, $farmer_msg);
                    mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$farmer_msg_clean')");
                }
                
                // Trigger low stock warning if new quantity <= 20
                if ($new_qty <= 20) {
                    $low_stock_msg = "⚠️ Low Stock Warning: Your crop " . $crop['crop_name'] . " only has " . $new_qty . " kg left. Consider restocking soon!";
                    $low_stock_msg_clean = mysqli_real_escape_string($conn, $low_stock_msg);
                    mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$low_stock_msg_clean')");
                }
                
                // Commit changes
                mysqli_commit($conn);
                
                if ($payment_method === 'UPI') {
                    header("Location: payment_gateway.php?order_id=" . $order_id);
                } else {
                    header("Location: my_orders.php?success=1");
                }
                exit();
            } else {
                // Stock deficiency
                mysqli_rollback($conn);
                header("Location: marketplace.php?err=insufficient_stock");
                exit();
            }
        } else {
            // Crop not found
            mysqli_rollback($conn);
            header("Location: marketplace.php?err=crop_not_found");
            exit();
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: marketplace.php?err=system_error");
        exit();
    }
} else {
    header("Location: marketplace.php");
    exit();
}
?>
