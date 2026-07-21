<?php
session_start();
include("../config/db.php");

// Session check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer"){
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// Fetch notifications
$user_id_clean = mysqli_real_escape_string($conn, $buyer_id);
$notif_query = "SELECT * FROM notifications WHERE user_id = '$user_id_clean' ORDER BY created_at DESC LIMIT 5";
$notif_res = mysqli_query($conn, $notif_query);
$unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$user_id_clean' AND is_read = 0";
$unread_count_res = mysqli_query($conn, $unread_count_query);
$unread_count = 0;
if ($unread_count_res) {
    $unread_count_row = mysqli_fetch_assoc($unread_count_res);
    $unread_count = (int)$unread_count_row['count'];
}

// UPI Payment Submission processor
if (isset($_POST['submit_payment'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $txn_id = mysqli_real_escape_string($conn, trim($_POST['txn_id']));
    
    if (empty($txn_id)) {
        header("Location: my_orders.php?err=empty_txn");
        exit();
    }
    
    $update_pay_sql = "UPDATE orders SET is_paid = 1, payment_txn = '$txn_id' WHERE id = '$order_id' AND buyer_id = '$buyer_id'";
    if (mysqli_query($conn, $update_pay_sql)) {
        // Fetch farmer ID for notification
        $farmer_query = "SELECT c.farmer_id, c.crop_name FROM orders o JOIN crops c ON o.crop_id = c.id WHERE o.id = '$order_id'";
        $farmer_res = mysqli_query($conn, $farmer_query);
        if ($farmer_res && mysqli_num_rows($farmer_res) > 0) {
            $farmer_row = mysqli_fetch_assoc($farmer_res);
            $farmer_id = $farmer_row['farmer_id'];
            $crop_name = $farmer_row['crop_name'];
            
            $pay_msg = "<i class='ph-duotone ph-credit-card'></i> Payment Settled: Buyer has submitted UPI Transaction ID $txn_id for Order #$order_id ($crop_name). Please verify and complete the fulfillment!";
            $pay_msg_clean = mysqli_real_escape_string($conn, $pay_msg);
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$pay_msg_clean')");
        }
        header("Location: my_orders.php?payment_success=1");
        exit();
    } else {
        header("Location: my_orders.php?err=payment_failed");
        exit();
    }
}

// Order cancellation processor (transaction-safe)
if (isset($_POST['cancel_order'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    try {
        // Fetch order details (row locking)
        $order_sql = "SELECT o.*, c.farmer_id FROM orders o JOIN crops c ON o.crop_id = c.id WHERE o.id = '$order_id' AND o.buyer_id = '$buyer_id' AND o.status = 'pending' FOR UPDATE";
        $order_res = mysqli_query($conn, $order_sql);
        if ($order_res && mysqli_num_rows($order_res) > 0) {
            $order_row = mysqli_fetch_assoc($order_res);
            $crop_id = $order_row['crop_id'];
            $qty_to_restore = (int)$order_row['quantity'];
            $farmer_id = $order_row['farmer_id'];
            
            // Restore crop quantity back to stock
            $restore_sql = "UPDATE crops SET quantity = quantity + $qty_to_restore WHERE id = '$crop_id'";
            mysqli_query($conn, $restore_sql);
            
            // Mark order cancelled
            $cancel_sql = "UPDATE orders SET status = 'cancelled' WHERE id = '$order_id'";
            mysqli_query($conn, $cancel_sql);
            
            // Send real-time notification to the Farmer
            $buyer_name = mysqli_real_escape_string($conn, $_SESSION['name']);
            $msg = "Order #$order_id has been cancelled by the buyer $buyer_name. Stock of $qty_to_restore kg has been returned to your inventory.";
            $msg_clean = mysqli_real_escape_string($conn, $msg);
            $notif_sql = "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$msg_clean')";
            mysqli_query($conn, $notif_sql);
            
            mysqli_commit($conn);
            header("Location: my_orders.php?cancelled=1");
            exit();
        } else {
            mysqli_rollback($conn);
            header("Location: my_orders.php?err=cannot_cancel");
            exit();
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: my_orders.php?err=system_error");
        exit();
    }
}

// Fetch all orders placed by this buyer
$sql = "SELECT o.*, c.crop_name, c.price as listing_price, c.farmer_id, u.name as farmer_name, u.email as farmer_email, u.mobile_no, u.upi_id
        FROM orders o 
        JOIN crops c ON o.crop_id = c.id 
        JOIN users u ON c.farmer_id = u.id 
        WHERE o.buyer_id = '$buyer_id' 
        ORDER BY o.id DESC";

$result = mysqli_query($conn, $sql);

// Success & error banners check
$show_success = isset($_GET['success']);
$show_cancelled = isset($_GET['cancelled']);
$show_error = isset($_GET['err']);

// Logistics progress render helper
function renderLogisticsTimeline($conn, $order_id, $status) {
    if (strtolower($status) === 'cancelled') {
        echo '<div class="timeline-wrapper">';
        echo '<div class="timeline-title">Logistics Status Timeline</div>';
        echo '<div style="background: rgba(239, 68, 68, 0.08); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-sm); padding: 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">';
        echo '<i class="ph-duotone ph-x-circle"></i> Order Cancelled & Items Returned to Stock';
        echo '</div>';
        echo '</div>';
        return;
    }
    
    // Fetch tracking ledger from DB
    $sql = "SELECT * FROM order_tracking WHERE order_id = '$order_id' ORDER BY id ASC";
    $res = mysqli_query($conn, $sql);
    
    echo '<div class="timeline-wrapper" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.5) 0%, #ffffff 100%); border-radius: var(--radius-md); border: 1px solid var(--border); padding: 16px; margin-top: 16px;">';
    echo '<div class="timeline-title" style="font-size: 14px; font-weight: 700; color: var(--text-dark); margin-bottom: 16px; display: flex; align-items: center; gap: 6px;"><i class="ph-duotone ph-map-pin-line" style="color: var(--primary);"></i> Strict Logistics Ledger</div>';
    
    if (mysqli_num_rows($res) > 0) {
        echo '<div style="position: relative; padding-left: 20px; border-left: 2px dashed rgba(99, 102, 241, 0.3);">';
        
        while ($track = mysqli_fetch_assoc($res)) {
            $is_final = ($track['tracking_status'] === 'Delivered');
            $icon = $is_final ? 'ph-check-circle' : 'ph-package';
            $color = $is_final ? '#10b981' : 'var(--primary)';
            $bg = $is_final ? 'rgba(16,185,129,0.1)' : 'var(--light-bg)';
            
            echo '<div style="position: relative; margin-bottom: 16px;">';
            // Dot
            echo '<div style="position: absolute; left: -26px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: ' . $color . '; box-shadow: 0 0 0 3px white, 0 0 10px ' . $color . ';"></div>';
            // Content
            echo '<div style="background: ' . $bg . '; padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(0,0,0,0.05);">';
            echo '<div style="font-size: 13px; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; justify-content: space-between;">';
            echo '<span>' . htmlspecialchars($track['tracking_status']) . '</span>';
            echo '<span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">' . date("M j, g:i A", strtotime($track['created_at'])) . '</span>';
            echo '</div>';
            echo '<div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; display: flex; gap: 8px; align-items: center;">';
            echo '<span><i class="ph-duotone ph-buildings"></i> ' . htmlspecialchars($track['location'] ?? 'Unknown Node') . '</span>';
            if ($track['updated_by_role'] === 'delivery_partner') {
                echo '<span style="background: rgba(56, 189, 248, 0.1); color: #0284c7; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;">Verified by Logistics</span>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div style="font-size: 13px; color: var(--text-muted); text-align: center; padding: 10px;"><i class="ph-duotone ph-spinner ph-spin"></i> Awaiting logistics handover...</div>';
    }
    
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .horizontal-order-list {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .horizontal-order-card {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            background: var(--light-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.4);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0,0,0,0.02);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease, border-color 0.4s ease;
        }
        .horizontal-order-card:hover {
            transform: translateY(-4px) scale(1.005);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08), 0 8px 16px rgba(16, 185, 129, 0.06);
            border-color: rgba(16, 185, 129, 0.4);
        }
        @media (min-width: 1024px) {
            .horizontal-order-card {
                grid-template-columns: 1.2fr 1.5fr 1.3fr 1fr;
                align-items: start;
            }
        }
        .order-col-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
        }
        .order-col-timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
        }
        .order-col-payment {
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
        }
        .order-col-grower {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        @media (min-width: 1024px) {
            .order-col-info, .order-col-timeline, .order-col-payment {
                border-bottom: none;
                padding-bottom: 0;
                border-right: 1px solid var(--border);
                padding-right: 20px;
            }
        }
        .order-col-timeline .timeline-wrapper {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            margin-top: 0 !important;
        }
        .order-col-timeline .timeline-title {
            font-size: 13px !important;
            margin-bottom: 12px !important;
        }
    </style>
</head>
<body>

    <header class="navbar">
        <a href="marketplace.php" class="navbar-brand">
            <span><i class='ph-duotone ph-plant'></i></span> AgroNava
        </a>
        <button class="navbar-toggle" id="navbar-toggle-btn" aria-label="Toggle navigation">
            <span>☰</span>
        </button>
        <div class="navbar-menu" id="navbar-menu-container">
            <a href="marketplace.php" style="color: var(--text-muted); font-weight: 600;">Marketplace</a>
            <a href="my_orders.php" style="color: var(--secondary); font-weight: 700;">My Orders</a>
            <a href="../market_prices.php" style="color: var(--text-muted); font-weight: 600;">Live Prices</a>
            <a href="../admin_complaints.php" style="color: #ef4444; font-weight: 700;"><i class='ph-duotone ph-shield-check'></i> Dispute Admin</a>
            
            <!-- Glowing Notification Bell -->
            <div class="notif-bell-container" id="notif-bell-btn">
                <span class="notif-bell-icon"><i class='ph-duotone ph-bell'></i></span>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
                
                <!-- Notification Dropdown -->
                <div class="notif-dropdown" id="notif-dropdown-menu">
                    <div class="notif-dropdown-header">
                        <span>Notifications</span>
                        <?php if ($unread_count > 0): ?>
                            <button onclick="markAllNotificationsRead(event)">Mark all read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown-body">
                        <?php 
                        if ($notif_res && mysqli_num_rows($notif_res) > 0) {
                            while ($notif = mysqli_fetch_assoc($notif_res)) {
                                echo get_notification_html($notif);
                            }
                        } else {
                            echo '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">No new alerts.</div>';
                        }
                        ?>
                    </div>
                    <div class="notif-dropdown-footer">
                        <button onclick="window.location.reload();">Refresh Drawer</button>
                    </div>
                </div>
            </div>

            <div class="user-badge">
                <span><i class='ph-duotone ph-shopping-cart'></i></span> <?php echo htmlspecialchars($_SESSION['name']); ?> (Buyer)
            </div>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <!-- Content -->
    <div class="grid-container animate-fade">
        
        <?php if($show_success) { ?>
            <div style="background: var(--success-light); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--primary-hover); padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;"><i class='ph-duotone ph-party-popper'></i></span>
                <div>
                    <h4 style="color: var(--primary-hover); margin: 0;">Order Placed Successfully!</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Your request has been sent directly to the farmer. Check status updates below.</p>
                </div>
            </div>
        <?php } ?>

        <?php if($show_cancelled) { ?>
            <div style="background: var(--danger-light); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;"><i class='ph-duotone ph-x-circle'></i></span>
                <div>
                    <h4 style="color: #ef4444; margin: 0;">Order Cancelled!</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Order status has been updated to Cancelled. Crop inventory stock has been restored.</p>
                </div>
            </div>
        <?php } ?>

        <?php if($show_error) { ?>
            <div style="background: var(--warning-light); border: 1px solid rgba(245, 158, 11, 0.2); color: #d97706; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;"><i class='ph-duotone ph-warning'></i></span>
                <div>
                    <h4 style="color: #d97706; margin: 0;">Operation Warning</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Unable to cancel order. The shipment may already be accepted, processed or shipped by the grower.</p>
                </div>
            </div>
        <?php } ?>

        <?php if(isset($_GET['payment_success'])) { ?>
            <div style="background: var(--success-light); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--primary-hover); padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;"><i class='ph-duotone ph-credit-card'></i></span>
                <div>
                    <h4 style="color: var(--primary-hover); margin: 0;">Payment Submitted Successfully!</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">The transaction reference ID has been recorded. The grower has been notified to verify and ship.</p>
                </div>
            </div>
        <?php } ?>

        <?php if(isset($_GET['err']) && $_GET['err'] == 'empty_txn') { ?>
            <div style="background: var(--warning-light); border: 1px solid rgba(245, 158, 11, 0.2); color: #d97706; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;"><i class='ph-duotone ph-warning'></i></span>
                <div>
                    <h4 style="color: #d97706; margin: 0;">Missing Transaction ID</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Please enter a valid transaction reference ID to log your UPI settlement.</p>
                </div>
            </div>
        <?php } ?>

        <?php if(isset($_GET['err']) && $_GET['err'] == 'payment_failed') { ?>
            <div style="background: var(--danger-light); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;"><i class='ph-duotone ph-x-circle'></i></span>
                <div>
                    <h4 style="color: #ef4444; margin: 0;">Payment Logging Failed</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">An error occurred while updating the payment details. Please try again later.</p>
                </div>
            </div>
        <?php } ?>

        <div style="margin-bottom: 32px;">
            <h1 style="font-size: 32px; color: var(--dark);">My Order History</h1>
            <p style="color: var(--text-muted);">Track shipment statuses, review transaction totals, and contact your crop growers directly</p>
        </div>

        <?php if(mysqli_num_rows($result) > 0) { ?>
            <div class="horizontal-order-list">
                <?php while($row = mysqli_fetch_assoc($result)) { 
                    $qty = (int)$row['quantity'];
                    $price = (int)$row['price'];
                    $transport_cost = isset($row['transport_cost']) ? (int)$row['transport_cost'] : 0;
                    $total = ($qty * $price) + $transport_cost;
                    $status = strtolower($row['status']);
                    
                    // Assign class to status badge
                    $badge_class = "badge-pending";
                    if ($status == "shipped") $badge_class = "badge-shipped";
                    if ($status == "delivered") $badge_class = "badge-delivered";

                    $details = null;
                    if (!empty($row['delivery_details'])) {
                        $details = json_decode($row['delivery_details'], true);
                        $details['tracking_status'] = $row['tracking_status'];
                    }
                    
                    // Fetch return logistics record if exists
                    $rl_data = null;
                    $rl_q = mysqli_query($conn, "SELECT * FROM return_logistics WHERE order_id = '{$row['id']}' LIMIT 1");
                    if ($rl_q && mysqli_num_rows($rl_q) > 0) {
                        $rl_data = mysqli_fetch_assoc($rl_q);
                    }

                    $comp_check = mysqli_query($conn, "SELECT status, ai_result FROM complaints WHERE order_id = '".$row['id']."'");
                    $complaint_filed = ($comp_check && mysqli_num_rows($comp_check) > 0);
                    if ($complaint_filed) {
                        $complaint_row = mysqli_fetch_assoc($comp_check);
                        $c_status = strtolower($complaint_row['status']);
                        $status_color = "#f59e0b";
                        if ($c_status === 'approved') $status_color = "#10b981";
                        if ($c_status === 'rejected') $status_color = "#ef4444";
                    }
                ?>
                    <div class="glass-card horizontal-order-card animate-slide">
                        
                        <!-- Column 1: Order details -->
                        <div class="order-col-info">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <span style="font-size: 13px; font-weight: 700; color: var(--text-muted);">
                                        Order #<?php echo $row['id']; ?>
                                    </span>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        🏷️ <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </div>
                                <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 12px;">
                                    <i class='ph-duotone ph-plant' style="color: var(--primary);"></i> <?php echo htmlspecialchars($row['crop_name']); ?>
                                </h3>
                            </div>
                            
                            <div style="background: rgba(15, 23, 42, 0.02); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                                    <span style="color: var(--text-muted);">Quantity ordered:</span>
                                    <span style="font-weight: 700; color: var(--dark);"><?php echo $qty; ?> kg</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                                    <span style="color: var(--text-muted);">Unit Price:</span>
                                    <span style="font-weight: 600; color: var(--dark);">₹<?php echo $price; ?> / kg</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; align-items: center;">
                                    <span style="color: var(--text-muted);">Transport Cost:</span>
                                    <span style="font-weight: 600; color: #f59e0b;">+ ₹<?php echo $transport_cost; ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 14px; padding-top: 6px; border-top: 1px dashed var(--border); font-weight: 800; color: var(--dark);">
                                    <span>Total Price:</span>
                                    <span>₹<?php echo number_format($total); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($details): ?>
                                <button type="button" class="btn btn-secondary btn-breakdown-toggle" 
                                        onclick="openBreakdownModal(<?php echo $row['id']; ?>, <?php echo htmlspecialchars(json_encode($details)); ?>, <?php echo htmlspecialchars($row['delivery_route'] ?: 'null'); ?>, <?php echo $rl_data ? htmlspecialchars(json_encode($rl_data)) : 'null'; ?>)" 
                                        style="width: 100%; font-size: 12px; padding: 8px; font-weight: 700; justify-content: center; gap: 4px;">
                                    📂 View Breakdown
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Column 2: Logistics timeline -->
                        <div class="order-col-timeline">
                            <?php renderLogisticsTimeline($conn, $row['id'], $row['status']); ?>
                        </div>

                        <!-- Column 3: Payment & Verification -->
                        <div class="order-col-payment">
                            <!-- Paid status or UPI payment drawer -->
                            <?php if ($row['is_paid'] == 1): ?>
                                <div style="border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-sm); background: linear-gradient(135deg, rgba(16, 185, 129, 0.03) 0%, rgba(52, 211, 153, 0.03) 100%); padding: 12px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 6px;">
                                    <div style="font-size: 14px; font-weight: 700; color: #10b981; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                        <span><i class='ph-duotone ph-shield-check'></i></span> Paid & Settled
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); display: block;">
                                        Txn ID: <strong><?php echo htmlspecialchars($row['payment_txn']); ?></strong>
                                    </span>
                                </div>
                            <?php else: ?>
                                <?php if ($status !== 'cancelled'): ?>
                                    <div class="payment-drawer-container" style="border: 1px solid rgba(99, 102, 241, 0.2); border-radius: var(--radius-sm); background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(168, 85, 247, 0.03) 100%); padding: 12px; overflow: hidden; transition: all 0.3s ease;">
                                        <div class="payment-drawer-header" onclick="togglePaymentDrawer(<?php echo $row['id']; ?>)" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">
                                            <span style="font-size: 12px; font-weight: 700; color: #4f46e5; display: flex; align-items: center; gap: 4px;">
                                                <i class='ph-duotone ph-credit-card'></i> Pay via UPI QR
                                            </span>
                                            <span id="drawer-arrow-<?php echo $row['id']; ?>" style="font-size: 11px; transition: transform 0.3s; transform: rotate(0deg); color: #4f46e5;">▼</span>
                                        </div>
                                        
                                        <div id="drawer-body-<?php echo $row['id']; ?>" class="payment-drawer-body" style="display: none; margin-top: 12px; text-align: center; border-top: 1px dashed rgba(99, 102, 241, 0.15); padding-top: 12px;">
                                            <div style="background: white; border: 1px solid var(--border); padding: 8px; border-radius: var(--radius-sm); display: inline-block; box-shadow: 0 4px 10px rgba(0,0,0,0.03); margin-bottom: 8px;">
                                                <?php
                                                $farmer_upi = 'agronava@okaxis';
                                                if (isset($row['upi_id']) && !empty($row['upi_id'])) {
                                                    $farmer_upi = $row['upi_id'];
                                                } else if (isset($row['mobile_no']) && !empty($row['mobile_no'])) {
                                                    $farmer_upi = $row['mobile_no'] . '@ybl';
                                                }
                                                $farmer_pn = rawurlencode($row['farmer_name']);
                                                $upi_uri = "upi://pay?pa=" . $farmer_upi . "&pn=" . $farmer_pn . "&am=" . $total . "&cu=INR&tn=Order_" . $row['id'];
                                                $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($upi_uri);
                                                ?>
                                                <img src="<?php echo $qr_api; ?>" alt="UPI QR Code" style="width: 110px; height: 110px; display: block; margin: 0 auto;">
                                            </div>
                                            <p style="font-size: 10px; color: var(--text-muted); margin-bottom: 8px; line-height: 1.3;">
                                                Pay <strong>₹<?php echo number_format($total); ?></strong> to <strong><?php echo htmlspecialchars($row['farmer_name']); ?></strong>.
                                            </p>
                                            
                                            <form action="my_orders.php" method="POST">
                                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                                <div style="text-align: left; margin-bottom: 8px;">
                                                    <input type="text" name="txn_id" placeholder="12-digit transaction ID" required 
                                                           style="width: 100%; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border); font-family: inherit; font-size: 12px; background: rgba(255,255,255,0.9); box-sizing: border-box;">
                                                </div>
                                                <button type="submit" name="submit_payment" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 8px; font-size: 12px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border: none;">
                                                    Verify Payment <i class='ph-duotone ph-credit-card'></i>
                                                </button>
                                            </form>
                                            <div style="margin-top: 8px; border-top: 1px dashed rgba(99, 102, 241, 0.15); padding-top: 8px;">
                                                <a href="payment_gateway.php?order_id=<?php echo $row['id']; ?>" class="btn btn-primary" style="display: flex; justify-content: center; padding: 6px; font-size: 11px; background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%); border: none;">
                                                    <i class='ph-duotone ph-lightning'></i> Open Checkout
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Verification Handover QR code -->
                            <?php if ($status !== 'delivered' && $status !== 'cancelled'): ?>
                                <div style="border: 1px dashed var(--border); border-radius: var(--radius-sm); background: #fff; padding: 12px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;">
                                    <span style="font-size: 11px; font-weight: 700; color: var(--secondary); display: block; text-transform: uppercase;"><i class='ph-duotone ph-handshake'></i> Verification QR</span>
                                    <?php
                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                                    $host = $_SERVER['HTTP_HOST'];
                                    $base_url = $protocol . $host;
                                    if (strpos($_SERVER['REQUEST_URI'], '/farm_portal/') !== false) {
                                        $base_url .= '/farm_portal/';
                                    } else {
                                        $base_url .= '/';
                                    }
                                    $verify_url = $base_url . "farmer/verify_delivery.php?order_id=" . $row['id'];
                                    $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verify_url);
                                    ?>
                                    <a href="<?php echo $verify_url; ?>" style="display: inline-block; cursor: pointer; border: 1px solid var(--border); padding: 4px; border-radius: var(--radius-sm); background: white;" title="Click to Emulate Scan">
                                        <img src="<?php echo $qr_api; ?>" alt="Verification QR" style="width: 80px; height: 80px; display: block; margin: 0 auto;">
                                    </a>
                                    <span style="font-size: 9px; color: var(--text-muted); display: block; line-height: 1.2;">
                                        Farmer can scan QR or click to verify handover instantly!
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Column 4: Grower & Action buttons -->
                        <div class="order-col-grower">
                            <div style="background: var(--light-bg); border-radius: var(--radius-sm); padding: 12px; border: 1px solid var(--border);">
                                <h4 style="font-size: 11px; color: var(--secondary); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                                    👨‍🌾 Grower Information
                                </h4>
                                <p style="font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 2px;">
                                    <a href="../farmer/profile.php?id=<?php echo $row['farmer_id']; ?>" style="color: var(--secondary); text-decoration: underline;">
                                        <?php echo htmlspecialchars($row['farmer_name']); ?>
                                    </a>
                                </p>
                                <p style="font-size: 11px; color: var(--text-muted); word-break: break-all;">
                                    ✉️ <?php echo htmlspecialchars($row['farmer_email']); ?>
                                </p>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <!-- Quality Passport link -->
                                <?php if ($status !== 'pending_payment' && $status !== 'cancelled'): ?>
                                    <a class="btn btn-secondary" style="justify-content: center; padding: 8px 12px; font-size: 11.5px; border: 1px solid rgba(16, 185, 129, 0.3) !important; color: #10b981 !important; background: rgba(16, 185, 129, 0.02);" 
                                       href="quality_passport.php?order_id=<?php echo $row['id']; ?>">
                                        <i class='ph-duotone ph-shield-check'></i> View Quality Passport Trace
                                    </a>
                                <?php endif; ?>

                                <!-- Invoice PDF link -->
                                <a class="btn btn-secondary" style="justify-content: center; padding: 8px 12px; font-size: 11.5px;" 
                                   href="invoice.php?id=<?php echo $row['id']; ?>">
                                    🖨️ View & Print Trade Invoice
                                </a>

                                <!-- Damage Claim or Report Damage -->
                                <?php if ($complaint_filed) { ?>
                                    <div style="padding: 8px 10px; border-radius: var(--radius-sm); background: rgba(15, 23, 42, 0.03); border: 1px solid var(--border); font-size: 11px; text-align: left;">
                                        <span style="font-weight: 700; color: var(--secondary); display: block; margin-bottom: 2px;"><i class='ph-duotone ph-warning'></i> Damage Claim Status</span>
                                        <span style="color: var(--text-muted); display: block; margin-bottom: 2px;">AI: <strong style="color: #4f46e5;"><?php echo htmlspecialchars($complaint_row['ai_result']); ?></strong></span>
                                        <span style="font-weight: 800; color: <?php echo $status_color; ?>; text-transform: uppercase;">Status: <?php echo htmlspecialchars($complaint_row['status']); ?></span>
                                    </div>
                                <?php } else if ($status === 'delivered') { ?>
                                    <a class="btn btn-danger" style="justify-content: center; padding: 8px 12px; font-size: 11.5px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;" 
                                       href="report_damage.php?order_id=<?php echo $row['id']; ?>">
                                        <i class='ph-duotone ph-warning'></i> Report Damage & AI Claim
                                    </a>
                                <?php } ?>

                                <!-- Rejection & Return Cargo -->
                                <?php if (($status === 'shipped' || $status === 'delivered') && !empty($row['delivery_details'])): ?>
                                    <?php if (mysqli_num_rows($check_return) == 0): ?>
                                        <form action="process_return.php" method="POST" onsubmit="return confirm('Are you sure you want to reject this delivery? This will register the return shipment and calculate transit penalty fees.');">
                                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="width: 100%; justify-content: center; padding: 8px 12px; font-size: 11.5px; background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); border: none;">
                                                <i class='ph-duotone ph-arrow-counter-clockwise'></i> Reject & Return Cargo
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div style="border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.03); padding: 8px; text-align: center; font-size: 11px; color: #b91c1c; font-weight: 700;">
                                            ↩️ Rejection Registered (<?php echo htmlspecialchars($ret_data['status']); ?>)
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Cancellation Button -->
                                <?php if ($status === 'pending'): ?>
                                    <form action="my_orders.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? It will return crop stock instantly.');">
                                        <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="cancel_order" class="btn btn-danger" style="width: 100%; justify-content: center; padding: 8px 12px; font-size: 11.5px;">
                                            <i class='ph-duotone ph-x-circle'></i> Cancel Order & Restock
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                <?php } ?>
            </div>

        <?php } else { ?>
            
            <div class="empty-state animate-slide">
                <div class="empty-state-icon">🛍️</div>
                <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 8px;">No Orders Placed Yet</h3>
                <p style="color: var(--text-muted); margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">
                    You haven't purchased any fresh produce yet. Head to the Marketplace to connect directly with our local farmers.
                </p>
                <a class="btn btn-primary" href="marketplace.php">Browse Marketplace <i class='ph-duotone ph-plant'></i></a>
            </div>

        <?php } ?>

    </div>

    <!-- Breakdown Modal Overlay -->
    <div class="modal-overlay" id="breakdown-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
        <div class="modal-content" style="background: white; width: 100%; max-width: 900px; border-radius: var(--radius-lg); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh; box-shadow: var(--shadow-xl); border: 1px solid var(--border);">
            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 20px; color: var(--dark); font-weight: 800; display: flex; align-items: center; gap: 8px; margin: 0;">
                    <i class="ph-duotone ph-receipt" style="color: var(--primary);"></i> Logistics & Pricing Breakdown
                </h3>
                <button type="button" onclick="closeBreakdownModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            <div class="modal-body" style="padding: 24px; overflow-y: auto; display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; text-align: left;">
                <!-- Left Column: Visual Stepper -->
                <div>
                    <h4 style="font-size: 14px; color: var(--secondary); margin: 0 0 12px 0; font-weight: 700; text-transform: uppercase;">📍 Logistics Pipeline Stepper</h4>
                    
                    <div style="background: var(--light-bg); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px 16px; margin-bottom: 12px; display: flex; flex-direction: column; justify-content: center; min-height: 180px; position: relative;">
                        <!-- Stepper Timeline Container -->
                        <div style="display: flex; align-items: center; justify-content: space-between; position: relative; padding: 20px 0;">
                            <!-- Progress Line Backer -->
                            <div style="position: absolute; top: 38px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1;"></div>
                            <!-- Progress Active Bar -->
                            <div id="modal-progress-bar" style="position: absolute; top: 38px; left: 10%; width: 0%; height: 4px; background: linear-gradient(to right, #10b981, #3b82f6); z-index: 2; transition: width 0.4s ease;"></div>
                            
                            <!-- Node 1: Grower -->
                            <div id="m-node-1" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 22%; text-align: center;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700;">🌾</div>
                                <span style="font-weight: 700; font-size: 10.5px; margin-top: 8px; color: var(--dark);">Farm Origin</span>
                            </div>

                            <!-- Node 2: CC -->
                            <div id="m-node-2" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 26%; text-align: center;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700;">🏢</div>
                                <span style="font-weight: 700; font-size: 10.5px; margin-top: 8px; color: var(--dark);" id="modal-stepper-cc">Collection Hub</span>
                                <span style="font-size: 9px; color: var(--text-muted); margin-top: 2px;" id="modal-stepper-cc-dist">0.00 km</span>
                            </div>

                            <!-- Node 3: WH -->
                            <div id="m-node-3" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 26%; text-align: center;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700;">🏬</div>
                                <span style="font-weight: 700; font-size: 10.5px; margin-top: 8px; color: var(--dark);" id="modal-stepper-wh">Central Depot</span>
                                <span style="font-size: 9px; color: var(--text-muted); margin-top: 2px;" id="modal-stepper-wh-dist">0.00 km</span>
                            </div>

                            <!-- Node 4: Buyer -->
                            <div id="m-node-4" style="display: flex; flex-direction: column; align-items: center; z-index: 3; width: 22%; text-align: center;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700;">🏠</div>
                                <span style="font-weight: 700; font-size: 10.5px; margin-top: 8px; color: var(--dark);">Destination</span>
                                <span style="font-size: 9px; color: var(--text-muted); margin-top: 2px;" id="modal-stepper-buyer-dist">0.00 km</span>
                            </div>
                        </div>
                    </div>

                    <div style="background: rgba(15, 23, 42, 0.03); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; font-size: 12px; line-height: 1.4;">
                        <strong>Pipeline Sequence:</strong>
                        <div style="margin-top: 6px; color: var(--text-muted);" id="modal-route-pipeline">
                            Farm ➔ Hub ➔ Warehouse ➔ Home
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Breakdown parameters -->
                <div>
                    <h4 style="font-size: 14px; color: var(--secondary); margin: 0 0 12px 0; font-weight: 700; text-transform: uppercase;">💰 Cost breakdown Parameters</h4>
                    <div id="modal-breakdown-details" style="font-size: 13px; line-height: 1.6; display: flex; flex-direction: column; gap: 6px; color: var(--text-main);">
                        <!-- Populate dynamically via JS -->
                    </div>
                    
                    <!-- Return Logistics Section -->
                    <div id="modal-return-details-section" style="display: none; border-top: 2px dashed #ef4444; margin-top: 16px; padding-top: 16px;">
                        <h4 style="font-size: 13px; color: #ef4444; margin: 0 0 8px 0; font-weight: 700; text-transform: uppercase;">↩️ Return Logistics Calculations</h4>
                        <div id="modal-return-details" style="font-size: 12.5px; line-height: 1.5; background: rgba(239, 68, 68, 0.05); padding: 12px; border-radius: var(--radius-sm); border: 1px solid rgba(239, 68, 68, 0.15); color: #7f1d1d;">
                            <!-- Populate dynamically via JS -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border); background: var(--light-bg); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="closeBreakdownModal()">Close Details</button>
            </div>
        </div>
    </div>

    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>
    <script>
    var breakdownMap = null;
    var mapMarkers = [];
    var mapRouteLine = null;

    function openBreakdownModal(orderId, details, route, retLogistics) {
        document.getElementById('breakdown-modal').style.display = 'flex';
        
        // Populate cost breakdown
        var detailsHtml = `
            <div style="display:flex; justify-content:space-between; font-weight:700; border-bottom:1px solid rgba(0,0,0,0.05); padding-bottom:6px; color:var(--secondary);"><span>Fee Component</span><span>Amount</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Base Delivery Fee:</span><strong>₹${parseFloat(details.base_fee).toFixed(2)}</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Distance Charge:</span><strong>₹${parseFloat(details.distance_cost).toFixed(2)} (${details.total_distance_km} km)</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Weight Surcharge:</span><strong>₹${parseFloat(details.weight_surcharge).toFixed(2)} (${details.weight_kg} kg)</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Vehicle Adjuster:</span><strong>x${parseFloat(details.vehicle_multiplier).toFixed(2)} (${details.vehicle_display || details.vehicle_type})</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Fuel Surcharge:</span><strong>₹${parseFloat(details.fuel_adjustment).toFixed(2)}</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Road Surcharge:</span><strong>₹${(details.road_flat + (details.subtotal * (details.road_multiplier - 1.0))).toFixed(2)}</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Zone Surcharge:</span><strong>₹${(details.zone_flat + (details.subtotal * (details.zone_multiplier - 1.0))).toFixed(2)}</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Weather & Seasonal:</span><strong>₹${(details.weather_flat + details.seasonal_flat + (details.subtotal * ((details.weather_multiplier * details.seasonal_multiplier) - 1.0))).toFixed(2)}</strong></div>
            <div style="display:flex; justify-content:space-between;"><span>Tolls & Packaging:</span><strong>₹${(parseFloat(details.toll_charges) + parseFloat(details.packaging_fee)).toFixed(2)}</strong></div>
            <div style="display:flex; justify-content:space-between; border-bottom: 1px dashed var(--border); padding-bottom: 6px; font-weight: 600; color: #10b981;"><span>Carbon Footprint:</span><span>${parseFloat(details.carbon_footprint_kg).toFixed(2)} kg CO2e</span></div>
            
            <div style="display:flex; justify-content:space-between; font-weight:800; font-size:15px; margin-top:6px; color:var(--dark);"><span>Total Delivery Cost:</span><span>₹${parseFloat(details.final_delivery_fee).toFixed(2)}</span></div>
            <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:var(--secondary); margin-top:2px;"><span>Transit ETA Window:</span><span>🚀 ${details.estimated_delivery_time || 'Pending'}</span></div>
        `;

        if (details.delivery_address_text) {
            detailsHtml += `<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); font-size:12.5px; color:var(--text-main); line-height: 1.4; text-align: left;">🏠 <strong>Delivery Destination:</strong> ${details.delivery_address_text}</div>`;
        }
        
        if (details.minimum_enforced) {
            detailsHtml += `<div style="font-size:11px; color:#d97706; font-weight:700; margin-top:4px;">⚠️ Minimum Fee Surcharge Enforced</div>`;
        }
        if (details.free_delivery_applied) {
            detailsHtml += `<div style="background:rgba(16, 185, 129, 0.1); color:#10b981; padding:4px; text-align:center; border-radius:4px; font-size:11px; font-weight:700; margin-top:6px;">🎉 Free Delivery Met!</div>`;
        }
        
        document.getElementById('modal-breakdown-details').innerHTML = detailsHtml;
        
        // Populate pipeline text
        var ccName = details.collection_center_name || "Mohali Hub";
        var whName = details.warehouse_name || "Chandigarh Depot";
        document.getElementById('modal-route-pipeline').innerHTML = `
            🌾 Grower Farm ➔ 🏢 ${ccName} ➔ 🏬 ${whName} ➔ 🏠 Customer Home
        `;

        // Return details
        var returnSect = document.getElementById('modal-return-details-section');
        if (retLogistics) {
            returnSect.style.display = 'block';
            document.getElementById('modal-return-details').innerHTML = `
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>Driver Pickup Surcharge (40% flat):</span><strong>₹${parseFloat(retLogistics.pickup_cost).toFixed(2)}</strong></div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>Depot Handling Surcharge:</span><strong>₹${parseFloat(retLogistics.warehouse_cost).toFixed(2)}</strong></div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>Farmer Admin Surcharge (60% flat):</span><strong>₹${parseFloat(retLogistics.farmer_return_cost).toFixed(2)}</strong></div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span>Return Trip Transshipment (50%):</span><strong>₹${parseFloat(retLogistics.return_transportation_charges).toFixed(2)}</strong></div>
                <div style="display:flex; justify-content:space-between; margin-top:6px; border-top:1px dashed rgba(239, 68, 68, 0.3); padding-top:6px; font-weight:800; font-size:14px;"><span>Total Return Surcharge:</span><strong>₹${parseFloat(retLogistics.total_return_cost).toFixed(2)}</strong></div>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; margin-top:4px; color:#b91c1c;">● Pipeline Status: ${retLogistics.status}</div>
            `;
        } else {
            returnSect.style.display = 'none';
        }

        // Update nodes and progress bar based on tracking status
        setTimeout(function() {
            var trackingStatus = details.tracking_status || 'Preparing';
            var nodes = [
                document.getElementById('m-node-1'),
                document.getElementById('m-node-2'),
                document.getElementById('m-node-3'),
                document.getElementById('m-node-4')
            ];

            // Reset nodes
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

            // Apply visual classes
            for (var i = 0; i < activeCount; i++) {
                if (!nodes[i]) continue;
                nodes[i].style.opacity = '1';
                var indicator = nodes[i].querySelector('div');
                if (indicator) {
                    if (i === activeCount - 1) {
                        indicator.style.background = '#3b82f6';
                        indicator.style.boxShadow = '0 0 12px rgba(59, 130, 246, 0.6)';
                    } else {
                        indicator.style.background = '#10b981';
                        indicator.style.boxShadow = '0 0 8px rgba(16, 185, 129, 0.4)';
                    }
                }
            }
            
            const progressBar = document.getElementById('modal-progress-bar');
            if (progressBar) progressBar.style.width = progressWidth;

            // Set distances
            const stepperCC = document.getElementById('modal-stepper-cc');
            if (stepperCC) stepperCC.textContent = ccName;

            const stepperCCDist = document.getElementById('modal-stepper-cc-dist');
            if (stepperCCDist) stepperCCDist.textContent = parseFloat(details.distance_farm_to_cc || 0).toFixed(2) + " km";

            const stepperWH = document.getElementById('modal-stepper-wh');
            if (stepperWH) stepperWH.textContent = whName;

            const stepperWHDist = document.getElementById('modal-stepper-wh-dist');
            if (stepperWHDist) stepperWHDist.textContent = parseFloat(details.distance_cc_to_wh || 0).toFixed(2) + " km";

            const stepperBuyerDist = document.getElementById('modal-stepper-buyer-dist');
            if (stepperBuyerDist) stepperBuyerDist.textContent = parseFloat(details.distance_wh_to_buyer || 0).toFixed(2) + " km";
        }, 150);
    }

    function closeBreakdownModal() {
        document.getElementById('breakdown-modal').style.display = 'none';
    }

    function togglePaymentDrawer(orderId) {
        var body = document.getElementById('drawer-body-' + orderId);
        var arrow = document.getElementById('drawer-arrow-' + orderId);
        if (body.style.display === 'none') {
            body.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
        } else {
            body.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    </script>
</body>
</html>
