<?php
session_start();
include("../config/db.php");

// Session validation
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "farmer"){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

$order_id = isset($_GET['order_id']) ? mysqli_real_escape_string($conn, $_GET['order_id']) : null;
if (isset($_POST['order_id'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
}

if (!$order_id) {
    die("Invalid request. Order ID is required.");
}

$error_msg = "";
$success = false;
$order_details = null;
$requires_otp = true;

// Fetch order details
$query = "SELECT o.*, c.crop_name, c.farmer_id, u.name as buyer_name, u.email as buyer_email
          FROM orders o
          JOIN crops c ON o.crop_id = c.id
          JOIN users u ON o.buyer_id = u.id
          WHERE o.id = '$order_id'";
          
$res = mysqli_query($conn, $query);

if ($res && mysqli_num_rows($res) > 0) {
    $order_details = mysqli_fetch_assoc($res);
    $qty = (int)$order_details['quantity'];
    $price = (int)$order_details['price'];
    $total_val = ($qty * $price) + (int)$order_details['transport_cost'];
    
    // Security check
    if ($order_details['farmer_id'] != $farmer_id) {
        $error_msg = "Access Denied: You do not have permissions to settle this order.";
        $requires_otp = false;
    } else {
        $status = strtolower($order_details['status']);
        
        if ($status === 'delivered') {
            $success = true;
            $requires_otp = false;
        } elseif ($status === 'cancelled') {
            $error_msg = "Operation Failed: This order has been cancelled and cannot be delivered.";
            $requires_otp = false;
        }
    }
} else {
    $error_msg = "Order record not found inside database.";
    $requires_otp = false;
}

// Process OTP Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $requires_otp) {
    $submitted_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    $correct_otp = $order_details['delivery_otp'];
    
    if ($submitted_otp === $correct_otp || empty($correct_otp)) {
        // OTP matched or no OTP was generated (legacy order)
        // Start secure SQL transaction
        mysqli_begin_transaction($conn);
        try {
            // Update order to 'delivered' and tracking to 'Delivered'
            $update_sql = "UPDATE orders SET status = 'delivered', tracking_status = 'Delivered' WHERE id = '$order_id'";
            mysqli_query($conn, $update_sql);
            
            // Add notifications
            $crop_name = $order_details['crop_name'];
            $buyer_id = $order_details['buyer_id'];
            
            $buyer_msg = "<i class='ph-duotone ph-party-popper'></i> Handover Confirmed! Your order #$order_id for $qty kg of $crop_name has been successfully delivered by the grower.";
            $buyer_msg_clean = mysqli_real_escape_string($conn, $buyer_msg);
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$buyer_msg_clean')");
            
            $farmer_msg = "<i class='ph-duotone ph-handshake'></i> Trade Finalized! Handover verified for Order #$order_id ($qty kg of $crop_name). Payment is now settled.";
            $farmer_msg_clean = mysqli_real_escape_string($conn, $farmer_msg);
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$farmer_msg_clean')");
            
            mysqli_commit($conn);
            $success = true;
            $requires_otp = false;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "A database transaction error occurred. Please try again.";
        }
    } else {
        $error_msg = "Invalid OTP provided. Please ask the buyer for the correct 6-digit Delivery OTP.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handover Verification | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    
    <style>
        .celebration-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 120px);
            padding: 24px;
        }
        
        .celebration-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg), 0 0 40px rgba(16, 185, 129, 0.15);
            padding: 48px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .celebration-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .success-seal {
            width: 80px;
            height: 80px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 28px;
            color: var(--primary);
            border: 2px solid rgba(16, 185, 129, 0.2);
            box-shadow: var(--shadow-glow);
            animation: popSeal 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }

        @keyframes popSeal {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .details-grid {
            background: rgba(15, 118, 110, 0.04);
            border: 1px dashed rgba(15, 118, 110, 0.15);
            border-radius: var(--radius-md);
            padding: 20px;
            margin: 28px 0;
            text-align: left;
        }

        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px dashed rgba(0,0,0,0.04);
        }

        .details-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .settled-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--success-light);
            color: var(--primary-hover);
            font-weight: 700;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: var(--radius-lg);
            margin-top: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(16, 185, 129, 0.15);
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <header class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span><i class='ph-duotone ph-plant'></i></span> AgroNava
        </a>
        <button class="navbar-toggle" id="navbar-toggle-btn" aria-label="Toggle navigation">
            <span>☰</span>
        </button>
        <div class="navbar-menu" id="navbar-menu-container">
            <a href="dashboard.php" style="color: var(--text-muted); font-weight: 600;">My Listings</a>
            <a href="orders.php" style="color: var(--text-muted); font-weight: 600;">Manage Orders</a>
            <a href="../market_prices.php" style="color: var(--text-muted); font-weight: 600;">Live Prices</a>
            <div class="user-badge">
                <span>👨‍<i class='ph-duotone ph-plant'></i></span> <?php echo htmlspecialchars($_SESSION['name']); ?>
            </div>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <div class="celebration-container">
        
        <?php if ($requires_otp && $order_details): ?>
            <div class="celebration-card animate-slide" style="border-color: rgba(245, 158, 11, 0.4);">
                <div class="success-seal" style="background: rgba(245, 158, 11, 0.1); color: #d97706; border-color: rgba(245, 158, 11, 0.3); box-shadow: none;">
                    🔐
                </div>
                
                <h1 style="font-size: 32px; color: #d97706; margin-bottom: 12px;">
                    Verify Delivery Handover
                </h1>
                
                <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 28px;">
                    Please ask the buyer for the 6-digit Delivery OTP to confirm they have received the order.
                </p>
                
                <?php if ($error_msg): ?>
                    <div style="background: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 20px; font-weight: 600;">
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="verify_delivery.php" style="max-width: 300px; margin: 0 auto;">
                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                    <input type="text" name="otp" placeholder="Enter 6-Digit OTP" required 
                           style="width: 100%; padding: 16px; font-size: 24px; text-align: center; letter-spacing: 4px; font-weight: 800; border-radius: var(--radius-md); border: 2px solid var(--border); margin-bottom: 16px;"
                           maxlength="6" autocomplete="off">
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 14px; font-size: 16px; font-weight: 700;">
                        Confirm Handover & Settle
                    </button>
                </form>
                
                <div style="margin-top: 24px;">
                    <a href="orders.php" style="color: var(--text-muted); text-decoration: underline; font-size: 14px;">Cancel & Go Back</a>
                </div>
            </div>
            
        <?php elseif ($success && $order_details): ?>
            
            <div class="celebration-card animate-slide">
                <div class="success-seal"><i class='ph-duotone ph-party-popper'></i></div>
                
                <h1 style="font-size: 32px; color: var(--secondary); margin-bottom: 12px;">
                    Handover Verified!
                </h1>
                
                <p style="color: var(--text-muted); font-size: 15px; max-width: 480px; margin: 0 auto;">
                    Direct Trade Handover is settled. The order status has been updated and confirmation alerts have been delivered.
                </p>

                <div class="settled-badge">
                    <i class='ph-duotone ph-shield-check'></i> Trade Completed & Settled
                </div>

                <div class="details-grid">
                    <div class="details-row">
                        <span style="color: var(--text-muted);">Order ID:</span>
                        <span style="font-weight: 700; color: var(--dark);">#<?php echo $order_details['id']; ?></span>
                    </div>
                    <div class="details-row">
                        <span style="color: var(--text-muted);">Fresh Produce Crop:</span>
                        <span style="font-weight: 700; color: var(--dark);"><i class='ph-duotone ph-plant'></i> <?php echo htmlspecialchars($order_details['crop_name']); ?></span>
                    </div>
                    <div class="details-row">
                        <span style="color: var(--text-muted);">Quantity Delivered:</span>
                        <span style="font-weight: 700; color: var(--dark);"><?php echo $qty; ?> kg</span>
                    </div>
                    <div class="details-row">
                        <span style="color: var(--text-muted);">Trade Revenue (incl. Transport):</span>
                        <span style="font-weight: 700; color: var(--secondary);">₹<?php echo number_format($total_val); ?></span>
                    </div>
                    <div class="details-row">
                        <span style="color: var(--text-muted);">Buyer Connection:</span>
                        <span style="font-weight: 600; color: var(--dark);"><?php echo htmlspecialchars($order_details['buyer_name']); ?> (<?php echo htmlspecialchars($order_details['buyer_email']); ?>)</span>
                    </div>
                </div>

                <div style="display: flex; gap: 16px; justify-content: center; margin-top: 36px;">
                    <a class="btn btn-primary" style="padding: 12px 24px; font-weight: 600;" href="orders.php">
                        📋 Manage Other Orders
                    </a>
                    <a class="btn btn-secondary" style="padding: 12px 24px; font-weight: 600;" href="dashboard.php">
                        🏛️ Go to Console Dashboard
                    </a>
                </div>
            </div>

        <?php else: ?>

            <div class="celebration-card animate-slide" style="border-color: rgba(239, 68, 68, 0.2);">
                <div class="success-seal" style="background: var(--danger-light); color: var(--danger); border-color: rgba(239, 68, 68, 0.2); box-shadow: none;">
                    <i class='ph-duotone ph-warning'></i>
                </div>
                
                <h1 style="font-size: 32px; color: var(--danger); margin-bottom: 12px;">
                    Verification Failed
                </h1>
                
                <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 28px;">
                    <?php echo htmlspecialchars($error_msg); ?>
                </p>

                <div style="display: flex; gap: 16px; justify-content: center;">
                    <a class="btn btn-danger" style="padding: 12px 24px; font-weight: 600;" href="orders.php">
                        📋 Back to Manage Orders
                    </a>
                    <a class="btn btn-secondary" style="padding: 12px 24px; font-weight: 600;" href="dashboard.php">
                        🏛️ Console Dashboard
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>
</body>
</html>
