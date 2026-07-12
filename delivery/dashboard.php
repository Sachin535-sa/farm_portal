<?php
session_start();
include("../config/db.php");

// Session check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "delivery_partner") {
    header("Location: ../auth/login_delivery.php");
    exit();
}

$partner_id = $_SESSION['user_id'];
$partner_name = $_SESSION['name'];

$message = "";
$success_msg = "";

// 1. Handle Package Pickup Confirmation
if (isset($_POST['record_pickup'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    
    // Simulate seal proof image upload
    $img_name = $_FILES['seal_proof']['name'];
    $tmp_name = $_FILES['seal_proof']['tmp_name'];
    $ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
    $new_img_name = "seal_" . $order_id . "_" . time() . "." . $ext;
    
    if (!file_exists("../uploads/seals/")) {
        mkdir("../uploads/seals/", 0777, true);
    }
    
    if (move_uploaded_file($tmp_name, "../uploads/seals/" . $new_img_name)) {
        mysqli_begin_transaction($conn);
        try {
            // Update order status to shipped, assign partner and store seal image
            mysqli_query($conn, "UPDATE orders 
                                 SET status = 'shipped', 
                                     tracking_status = 'Out for Delivery', 
                                     delivery_partner_id = '$partner_id', 
                                     delivery_proof_image = '$new_img_name' 
                                 WHERE id = '$order_id'");
            
            // Log notifications
            $order_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT buyer_id, quantity, crop_id FROM orders WHERE id = '$order_id'"));
            $buyer_id = $order_info['buyer_id'];
            
            $notif_msg = mysqli_real_escape_string($conn, "🚚 Package Dispatched: Order #$order_id is out for delivery. Logistics custody verified with package seal confirmation.");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$notif_msg')");
            
            mysqli_commit($conn);
            $success_msg = "Package pickup verified! Transit custody established.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Database update error.";
        }
    } else {
        $message = "Failed to upload seal verification proof.";
    }
}

// 2. Handle Delivery Completion via OTP
if (isset($_POST['verify_otp_delivery'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $entered_otp = mysqli_real_escape_string($conn, $_POST['delivery_otp']);
    
    // Fetch delivery details
    $order_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT o.*, c.crop_name, c.farmer_id FROM orders o JOIN crops c ON o.crop_id = c.id WHERE o.id = '$order_id'"));
    
    if ($order_info && $order_info['delivery_otp'] === $entered_otp) {
        mysqli_begin_transaction($conn);
        try {
            // Update order status to delivered
            mysqli_query($conn, "UPDATE orders SET status = 'delivered', tracking_status = 'Delivered' WHERE id = '$order_id'");
            
            $buyer_id = $order_info['buyer_id'];
            $farmer_id = $order_info['farmer_id'];
            $crop_name = $order_info['crop_name'];
            $qty = $order_info['quantity'];
            
            // Payout notification for farmer
            $farmer_msg = mysqli_real_escape_string($conn, "✅ Order Delivered: Order #$order_id ($qty kg of $crop_name) successfully delivered. Payout released to your linked account.");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$farmer_msg')");
            
            // Notification for buyer
            $buyer_msg = mysqli_real_escape_string($conn, "✅ Order Received: Package verified and delivered successfully. Thank you for direct trading!");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$buyer_msg')");
            
            mysqli_commit($conn);
            $success_msg = "Order #$order_id delivered successfully! Escrow payout released.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Database error during payout resolution.";
        }
    } else {
        $message = "🔒 Invalid Verification OTP code. Please verify with the customer.";
    }
}

// Fetch all active orders (assigned or available to pick up)
$orders_query = mysqli_query($conn, "SELECT o.*, c.crop_name, f.name as farmer_name, f.address as farmer_address, b.name as buyer_name, b.address as buyer_address
                                    FROM orders o 
                                    JOIN crops c ON o.crop_id = c.id 
                                    JOIN users f ON c.farmer_id = f.id 
                                    JOIN users b ON o.buyer_id = b.id
                                    WHERE o.status IN ('pending', 'shipped')
                                    ORDER BY o.id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Terminal | AgroNava</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.6">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-glow: rgba(56, 189, 248, 0.15);
            --slate-dark: #0f172a;
        }

        body {
            background-color: #0b0f19;
            color: #f1f5f9;
            font-family: 'Inter', sans-serif;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1.2fr;
            gap: 32px;
            max-width: 1300px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .order-card {
            background: rgba(30, 41, 59, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-md);
            padding: 24px;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: 0 0 25px var(--blue-glow);
            border-color: rgba(56, 189, 248, 0.3);
        }

        .badge-status {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-transit {
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.2);
        }

        .action-row {
            display: flex;
            gap: 16px;
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 18px;
        }

        .map-mock {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-md);
            padding: 24px;
            height: 380px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
        }

        .map-line {
            position: absolute;
            width: 70%;
            height: 4px;
            background: dashed #38bdf8;
            top: 50%;
            left: 15%;
            transform: translateY(-50%) rotate(-15deg);
            animation: routeAnim 3s infinite linear;
        }

        @keyframes routeAnim {
            0% { background-position: 0 0; }
            100% { background-position: 40px 0; }
        }

        .map-pin {
            position: absolute;
            font-size: 28px;
            z-index: 5;
        }

        .form-file {
            background: rgba(255, 255, 255, 0.05);
            border: 1px dashed rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            box-sizing: border-box;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <header class="navbar" style="background: rgba(11, 15, 25, 0.85); border-color: rgba(255, 255, 255, 0.08);">
        <a href="dashboard.php" class="navbar-brand" style="color: #38bdf8;">
            <span>🚚</span> Logistics Console
        </a>
        <div class="navbar-menu">
            <span class="user-badge" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.2);">
                Driver: <?php echo htmlspecialchars($partner_name); ?>
            </span>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <div class="dashboard-grid">
        
        <!-- Left Side: Order list -->
        <div>
            <h1 style="font-size: 32px; font-family: 'Outfit', sans-serif; margin-bottom: 8px;">Logistics Shipments</h1>
            <p style="color: #94a3b8; font-size: 14px; margin-bottom: 30px;">Manage farmer pickups and secure customer OTP dispatches.</p>

            <?php if ($message != "") { ?>
                <div class="alert-box"><?php echo $message; ?></div>
            <?php } ?>
            <?php if ($success_msg != "") { ?>
                <div class="alert-box" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #34d399;"><?php echo $success_msg; ?></div>
            <?php } ?>

            <?php if ($orders_query && mysqli_num_rows($orders_query) > 0) {
                while ($row = mysqli_fetch_assoc($orders_query)) {
                    $is_transit = ($row['status'] === 'shipped');
                    $badge_class = $is_transit ? 'badge-transit' : 'badge-pending';
                    $status_label = $is_transit ? 'In Transit' : 'Awaiting Pickup';
            ?>
                <div class="order-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <span style="font-size: 18px; font-weight: 800; color: white;">Order #<?php echo $row['id']; ?></span>
                        <span class="badge-status <?php echo $badge_class; ?>"><?php echo $status_label; ?></span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 14px;">
                        <div>
                            <h4 style="color: #38bdf8; margin-bottom: 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">1. Pickup (Grower)</h4>
                            <div style="font-weight: 700; margin-bottom: 4px;"><?php echo htmlspecialchars($row['farmer_name'] ?? ''); ?></div>
                            <div style="color: #94a3b8; line-height: 1.4;"><?php echo htmlspecialchars($row['farmer_address'] ?? ''); ?></div>
                        </div>
                        <div>
                            <h4 style="color: #38bdf8; margin-bottom: 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">2. Drop-off (Buyer)</h4>
                            <div style="font-weight: 700; margin-bottom: 4px;"><?php echo htmlspecialchars($row['buyer_name'] ?? ''); ?></div>
                            <div style="color: #94a3b8; line-height: 1.4;"><?php echo htmlspecialchars($row['buyer_address'] ?? ''); ?></div>
                        </div>
                    </div>

                    <div style="margin-top: 16px; background: rgba(0, 0, 0, 0.2); padding: 12px; border-radius: 8px; font-size: 13px; display: flex; justify-content: space-between;">
                        <span>Crop: <b><?php echo htmlspecialchars($row['crop_name']); ?></b> (<?php echo $row['quantity']; ?> kg)</span>
                        <span>Packaging: <b><?php echo htmlspecialchars($row['package_type'] ?? 'Standard'); ?></b></span>
                        <span style="color: #38bdf8; font-weight: 700;">Delivery Payout: ₹<?php echo number_format($row['transport_cost']); ?></span>
                    </div>

                    <!-- Interactive Custody Forms -->
                    <div class="action-row">
                        <?php if (!$is_transit) { ?>
                            <!-- Pickup form -->
                            <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 12px; align-items: center; width: 100%;">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <div style="flex: 1;">
                                    <input type="file" name="seal_proof" class="form-file" accept="image/*" required>
                                </div>
                                <button type="submit" name="record_pickup" class="btn btn-primary" style="background: #38bdf8; border-color: #38bdf8; padding: 12px 24px;">
                                    Confirm Package Seal & Pickup 📦
                                </button>
                            </form>
                        <?php } else { ?>
                            <!-- Delivery Verification OTP form -->
                            <form method="POST" style="display: flex; gap: 12px; align-items: center; width: 100%;">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <div style="flex: 1;">
                                    <input class="form-control" type="text" name="delivery_otp" placeholder="Enter Buyer Verification OTP (6-digits)" style="background: rgba(15, 23, 42, 0.6); border-color: rgba(255, 255, 255, 0.1); color: white;" required>
                                </div>
                                <button type="submit" name="verify_otp_delivery" class="btn btn-primary" style="background: #10b981; border-color: #10b981; padding: 12px 24px;">
                                    Verify OTP & Complete Settlement 🤝
                                </button>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div style="background: rgba(30, 41, 59, 0.2); padding: 40px; border-radius: 12px; text-align: center; border: 1px dashed rgba(255, 255, 255, 0.08);">
                    <span style="font-size: 40px; display: block; margin-bottom: 12px;">🚚</span>
                    <h3 style="font-size: 18px; color: #94a3b8;">No Active Deliveries</h3>
                    <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Logistics routes are currently optimized. Check back soon for new assignments.</p>
                </div>
            <?php } ?>
        </div>

        <!-- Right Side: Navigation Mapping & Optimization -->
        <div>
            <h3 style="font-size: 20px; font-family: 'Outfit', sans-serif; margin-bottom: 20px;">Route Optimization Engine</h3>
            
            <div class="map-mock">
                <!-- Visual Map Simulator -->
                <div style="position: absolute; inset: 0; background: radial-gradient(circle at 50% 50%, #1e293b 10%, #0f172a 90%); opacity: 0.9;"></div>
                
                <!-- Farm Pin -->
                <div class="map-pin" style="top: 60%; left: 15%; color: #fbbf24;">🚜</div>
                <div style="position: absolute; top: 72%; left: 12%; font-size: 11px; font-weight: 700; color: #94a3b8;">Punjab Farm Hub</div>
                
                <!-- Route Path -->
                <div class="map-line"></div>
                
                <!-- Destination Pin -->
                <div class="map-pin" style="top: 35%; right: 15%; color: #38bdf8;">🏢</div>
                <div style="position: absolute; top: 47%; right: 12%; font-size: 11px; font-weight: 700; color: #94a3b8;">Urban Warehouse Hub</div>

                <div style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 10; background: rgba(15, 23, 42, 0.85); padding: 10px 20px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); font-size: 12px; font-weight: 600; text-align: center; color: #38bdf8; backdrop-filter: blur(10px); white-space: nowrap;">
                    🚀 Optimal Path Sequenced (ETA: ~1h 45m)
                </div>
            </div>

            <!-- Route Performance Details Card -->
            <div class="glass-card" style="background: rgba(30, 41, 59, 0.2); border-color: rgba(255, 255, 255, 0.08); margin-top: 24px; padding: 20px;">
                <h4 style="font-size: 15px; margin-bottom: 12px; color: white;">Logistics Efficiency Diagnostics</h4>
                <ul style="padding-left: 18px; font-size: 13px; color: #94a3b8; line-height: 1.8;">
                    <li><b>Route Length:</b> Dynamic distance mapped.</li>
                    <li><b>Packaging:</b> Categorized cargo protection.</li>
                    <li><b>Dynamic Fuel index:</b> Standard local rates applied.</li>
                </ul>
            </div>
        </div>

    </div>

</body>
</html>
