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

// Calculate total driver earnings
$earnings_q = mysqli_query($conn, "SELECT SUM(transport_cost) as total_earnings FROM orders WHERE delivery_partner_id = '$partner_id' AND status = 'delivered'");
$earnings_row = mysqli_fetch_assoc($earnings_q);
$total_earnings = floatval($earnings_row['total_earnings'] ?? 0.0);

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
            
            // Add tracking ledger entry for pickup
            mysqli_query($conn, "INSERT INTO order_tracking (order_id, tracking_status, location, updated_by_role, updated_by_id) 
                                 VALUES ('$order_id', 'Out for Delivery', 'Local Farm Node', 'delivery_partner', '$partner_id')");
            
            $notif_msg = mysqli_real_escape_string($conn, "<i class='ph-duotone ph-truck'></i> Package Dispatched: Order #$order_id is out for delivery. Logistics custody verified with package seal confirmation.");
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
            
            // Add tracking ledger entry for final delivery
            mysqli_query($conn, "INSERT INTO order_tracking (order_id, tracking_status, location, updated_by_role, updated_by_id) 
                                 VALUES ('$order_id', 'Delivered', 'Buyer Location', 'delivery_partner', '$partner_id')");
            
            $buyer_id = $order_info['buyer_id'];
            $farmer_id = $order_info['farmer_id'];
            $crop_name = $order_info['crop_name'];
            $qty = $order_info['quantity'];
            
            // Payout notification for farmer
            $farmer_msg = mysqli_real_escape_string($conn, "<i class='ph-duotone ph-check-circle'></i> Order Delivered: Order #$order_id ($qty kg of $crop_name) successfully delivered. Payout released to your linked account.");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$farmer_msg')");
            
            // Notification for buyer
            $buyer_msg = mysqli_real_escape_string($conn, "<i class='ph-duotone ph-check-circle'></i> Order Received: Package verified and delivered successfully. Thank you for direct trading!");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$buyer_msg')");
            
            mysqli_commit($conn);
            $success_msg = "Order #$order_id delivered successfully! Escrow payout released.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Database error during payout resolution.";
        }
    } else {
        $message = "<i class='ph-duotone ph-lock-key'></i> Invalid Verification OTP code. Please verify with the customer.";
    }
}

// 3. Handle Intermediate Tracking Updates
if (isset($_POST['update_tracking_node'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['tracking_status']);
    
    mysqli_begin_transaction($conn);
    try {
        // Update main order status
        mysqli_query($conn, "UPDATE orders SET tracking_status = '$new_status' WHERE id = '$order_id'");
        
        // Add to tracking ledger
        $loc = ($new_status === 'At Collection Center') ? 'Regional Hub' : (($new_status === 'At Warehouse') ? 'Central Warehouse' : 'Transit Route');
        mysqli_query($conn, "INSERT INTO order_tracking (order_id, tracking_status, location, updated_by_role, updated_by_id) 
                             VALUES ('$order_id', '$new_status', '$loc', 'delivery_partner', '$partner_id')");
                             
        mysqli_commit($conn);
        $success_msg = "Logistics node updated to: " . htmlspecialchars($new_status);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Database error updating tracking node.";
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
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
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
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <header class="navbar" style="background: rgba(11, 15, 25, 0.85); border-color: rgba(255, 255, 255, 0.08);">
        <a href="dashboard.php" class="navbar-brand" style="color: #38bdf8;">
            <span><i class='ph-duotone ph-truck'></i></span> Logistics Console
        </a>
        <button class="navbar-toggle" id="navbar-toggle-btn" aria-label="Toggle navigation">
            <span>☰</span>
        </button>
        <div class="navbar-menu" id="navbar-menu-container">
            <span class="user-badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 700;">
                💰 Earnings: ₹<?php echo number_format($total_earnings, 2); ?>
            </span>
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
                <div class="order-card" style="cursor: pointer;" onclick="selectShipment(this, <?php echo $row['id']; ?>, <?php echo htmlspecialchars($row['delivery_route'] ?: 'null'); ?>, <?php echo htmlspecialchars($row['delivery_details'] ?: 'null'); ?>, '<?php echo $row['tracking_status']; ?>')">
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
                                    Confirm Package Seal & Pickup <i class='ph-duotone ph-package'></i>
                                </button>
                            </form>
                        <?php } else { ?>
                            <!-- Update Logistics Stage Form (Phase 4 Tracker) -->
                            <form method="POST" style="display: flex; gap: 12px; align-items: center; width: 100%; margin-bottom: 12px;">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <div style="flex: 1;">
                                    <select name="tracking_status" class="form-control" style="background: rgba(15, 23, 42, 0.6); border-color: rgba(255, 255, 255, 0.1); color: white;" required>
                                        <option value="" disabled selected>Update Intermediate Logistics Node...</option>
                                        <option value="At Collection Center">📍 Arrived at Regional Collection Center</option>
                                        <option value="At Warehouse">🏭 Arrived at Central Warehouse</option>
                                        <option value="Out for Delivery">🚚 Dispatched for Final Delivery</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_tracking_node" class="btn btn-secondary" style="border-color: #38bdf8; color: #38bdf8; padding: 12px 24px; background: transparent;">
                                    Log Transit Node
                                </button>
                            </form>
                            
                            <!-- Delivery Verification OTP form -->
                            <form method="POST" style="display: flex; gap: 12px; align-items: center; width: 100%; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 12px;">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <div style="flex: 1;">
                                    <input class="form-control" type="text" name="delivery_otp" placeholder="Enter Buyer Verification OTP (6-digits)" style="background: rgba(15, 23, 42, 0.6); border-color: rgba(255, 255, 255, 0.1); color: white;" required>
                                </div>
                                <button type="submit" name="verify_otp_delivery" class="btn btn-primary" style="background: #10b981; border-color: #10b981; padding: 12px 24px;">
                                    Verify OTP & Complete Settlement <i class='ph-duotone ph-handshake'></i>
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
                    <span style="font-size: 40px; display: block; margin-bottom: 12px;"><i class='ph-duotone ph-truck'></i></span>
                    <h3 style="font-size: 18px; color: #94a3b8;">No Active Deliveries</h3>
                    <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Logistics routes are currently optimized. Check back soon for new assignments.</p>
                </div>
            <?php } ?>
        </div>

        <!-- Right Side: Navigation Mapping & Optimization -->
        <div>
            <h3 style="font-size: 20px; font-family: 'Outfit', sans-serif; margin-bottom: 20px; color: white;">Route Optimization Engine</h3>
            
            <!-- Route Timeline Stepper Placeholder -->
            <div id="driver-stepper-placeholder" style="background: rgba(30, 41, 59, 0.2); padding: 40px; border-radius: 12px; text-align: center; border: 1px dashed rgba(255, 255, 255, 0.08); margin-bottom: 24px;">
                <span style="font-size: 40px; display: block; margin-bottom: 12px;"><i class='ph-duotone ph-map-pin'></i></span>
                <h3 style="font-size: 16px; color: #94a3b8;">No Route Loaded</h3>
                <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Click an active assignment card on the left to display its routing details.</p>
            </div>

            <!-- Visual Logistics Tracker Stepper -->
            <div id="driver-stepper-container" style="background: rgba(30, 41, 59, 0.45); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: var(--radius-md); padding: 24px 16px; margin-bottom: 24px; display: none;">
                <h4 style="font-size: 15px; margin: 0 0 20px 0; color: white; display: flex; align-items: center; gap: 6px;">
                    <i class="ph-duotone ph-map-pin" style="color: #38bdf8; font-size: 18px;"></i> Live Shipment Progress Stepper
                </h4>
                
                <div style="display: flex; align-items: center; justify-content: space-between; position: relative; padding: 20px 0;">
                    <!-- Connector Line -->
                    <div style="position: absolute; top: 38px; left: 10%; right: 10%; height: 4px; background: rgba(255, 255, 255, 0.1); z-index: 1;"></div>
                    <!-- Active Progress -->
                    <div id="driver-progress-bar" style="position: absolute; top: 38px; left: 10%; width: 0%; height: 4px; background: linear-gradient(to right, #38bdf8, #10b981); z-index: 2; transition: width 0.4s ease;"></div>
                    
                    <!-- Node 1: Farmer Pickup -->
                    <div id="d-node-1" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 22%; text-align: center; opacity: 0.4; transition: opacity 0.3s ease;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; transition: background 0.3s ease;">🌾</div>
                        <span style="font-weight: 700; font-size: 11px; margin-top: 8px; color: #f1f5f9; display: block;">Farm Origin</span>
                        <span style="font-size: 9.5px; color: #94a3b8; margin-top: 2px; display: block;">Awaiting Pickup</span>
                    </div>

                    <!-- Node 2: Collection Hub -->
                    <div id="d-node-2" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 26%; text-align: center; opacity: 0.4; transition: opacity 0.3s ease;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; transition: background 0.3s ease;">🏢</div>
                        <span style="font-weight: 700; font-size: 11px; margin-top: 8px; color: #f1f5f9; display: block;" id="d-node-cc-name">Collection Hub</span>
                        <span style="font-size: 9.5px; color: #94a3b8; margin-top: 2px; display: block;" id="d-node-cc-dist">0.00 km</span>
                    </div>

                    <!-- Node 3: Warehouse -->
                    <div id="d-node-3" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 26%; text-align: center; opacity: 0.4; transition: opacity 0.3s ease;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; transition: background 0.3s ease;">🏬</div>
                        <span style="font-weight: 700; font-size: 11px; margin-top: 8px; color: #f1f5f9; display: block;" id="d-node-wh-name">Central Depot</span>
                        <span style="font-size: 9.5px; color: #94a3b8; margin-top: 2px; display: block;" id="d-node-wh-dist">0.00 km</span>
                    </div>

                    <!-- Node 4: Buyer -->
                    <div id="d-node-4" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 22%; text-align: center; opacity: 0.4; transition: opacity 0.3s ease;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; transition: background 0.3s ease;">🏠</div>
                        <span style="font-weight: 700; font-size: 11px; margin-top: 8px; color: #f1f5f9; display: block;">Destination</span>
                        <span style="font-size: 9.5px; color: #94a3b8; margin-top: 2px; display: block;" id="d-node-buyer-dist">0.00 km</span>
                    </div>
                </div>
            </div>

            <!-- Route Performance Details Card -->
            <div class="glass-card" style="background: rgba(30, 41, 59, 0.35); border-color: rgba(255, 255, 255, 0.08); padding: 20px;">
                <h4 style="font-size: 15px; margin: 0 0 12px 0; color: white; display: flex; align-items: center; gap: 6px;">
                    <i class="ph-duotone ph-gauge" style="color: #38bdf8;"></i> Logistics Efficiency Diagnostics
                </h4>
                <ul id="diagnostics-list" style="padding-left: 18px; font-size: 13px; color: #94a3b8; line-height: 1.8; margin: 0;">
                    <li><b>Route Length:</b> Click a shipment card to map routing.</li>
                    <li><b>Packaging Cargo Protection:</b> Click a shipment card.</li>
                    <li><b>Dynamic Fuel Index Adjuster:</b> Click a shipment card.</li>
                </ul>
            </div>
        </div>

    </div>

    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>
    <script>
    function selectShipment(card, orderId, route, details, trackingStatus) {
        // Highlight active card
        document.querySelectorAll('.order-card').forEach(function(c) {
            c.style.borderColor = 'rgba(255, 255, 255, 0.08)';
            c.style.boxShadow = 'none';
        });
        card.style.borderColor = 'rgba(56, 189, 248, 0.5)';
        card.style.boxShadow = '0 0 25px rgba(56, 189, 248, 0.2)';

        var placeholder = document.getElementById('driver-stepper-placeholder');
        if (placeholder) placeholder.style.display = 'none';
        
        var container = document.getElementById('driver-stepper-container');
        if (container) container.style.display = 'block';

        var ccName = details ? (details.collection_center_name || "Mohali Hub") : "Mohali Hub";
        var whName = details ? (details.warehouse_name || "Chandigarh Depot") : "Chandigarh Depot";

        // Update Stepper Text/Distance
        if (details) {
            document.getElementById('d-node-cc-name').textContent = ccName;
            document.getElementById('d-node-cc-dist').textContent = parseFloat(details.distance_farm_to_cc || 0).toFixed(2) + " km";

            document.getElementById('d-node-wh-name').textContent = whName;
            document.getElementById('d-node-wh-dist').textContent = parseFloat(details.distance_cc_to_wh || 0).toFixed(2) + " km";

            document.getElementById('d-node-buyer-dist').textContent = parseFloat(details.distance_wh_to_buyer || 0).toFixed(2) + " km";
        }

        // Highlight nodes based on tracking status
        var nodes = [
            document.getElementById('d-node-1'),
            document.getElementById('d-node-2'),
            document.getElementById('d-node-3'),
            document.getElementById('d-node-4')
        ];

        // Reset all nodes
        nodes.forEach(function(node) {
            if (!node) return;
            node.style.opacity = '0.4';
            var indicator = node.querySelector('div');
            if (indicator) {
                indicator.style.background = '#475569';
                indicator.style.boxShadow = 'none';
            }
        });

        var progressWidth = '0%';
        var activeCount = 1;

        if (trackingStatus === 'At Collection Center') {
            activeCount = 2;
            progressWidth = '38%';
        } else if (trackingStatus === 'At Warehouse') {
            activeCount = 3;
            progressWidth = '71%';
        } else if (trackingStatus === 'Out for Delivery' || trackingStatus === 'Delivered') {
            activeCount = 4;
            progressWidth = '100%';
        } else {
            activeCount = 1;
            progressWidth = '0%';
        }

        // Apply visual states
        for (var i = 0; i < activeCount; i++) {
            if (!nodes[i]) continue;
            nodes[i].style.opacity = '1';
            var indicator = nodes[i].querySelector('div');
            if (indicator) {
                if (i === activeCount - 1) {
                    indicator.style.background = '#38bdf8';
                    indicator.style.boxShadow = '0 0 14px rgba(56, 189, 248, 0.6)';
                } else {
                    indicator.style.background = '#10b981';
                    indicator.style.boxShadow = '0 0 10px rgba(16, 185, 129, 0.4)';
                }
            }
        }
        
        var progressBar = document.getElementById('driver-progress-bar');
        if (progressBar) progressBar.style.width = progressWidth;

        // Update Diagnostics UI Card
        if (details) {
            var carbon = parseFloat(details.carbon_footprint_kg).toFixed(2);
            var packagingStr = details.packaging_type + " (₹" + details.packaging_fee + ")";
            var fuelStr = "₹" + parseFloat(details.fuel_adjustment).toFixed(2) + " (" + details.fuel_type.toUpperCase() + ")";
            document.getElementById('diagnostics-list').innerHTML = `
                <li>🏁 <b>Total Route Length:</b> ${details.total_distance_km} km</li>
                <li>📦 <b>Packaging Cargo Protection:</b> ${packagingStr}</li>
                <li>⛽ <b>Dynamic Fuel Index Adjuster:</b> ${fuelStr}</li>
                <li>🌱 <b>Carbon Footprint:</b> ${carbon} kg CO2e</li>
                <li>🚀 <b>Priority Level:</b> ${details.delivery_priority.toUpperCase()}</li>
                <li>⏱️ <b>ETA Window:</b> ${details.estimated_delivery_time || 'Pending'}</li>
            `;
        }
    }

    // Auto-select first shipment on load
    window.addEventListener('DOMContentLoaded', function() {
        var firstCard = document.querySelector('.order-card');
        if (firstCard) {
            firstCard.click();
        }
    });
    </script>
</body>
</html>
