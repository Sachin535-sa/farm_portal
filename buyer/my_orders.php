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
            
            $pay_msg = "💳 Payment Settled: Buyer has submitted UPI Transaction ID $txn_id for Order #$order_id ($crop_name). Please verify and complete the fulfillment!";
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
function renderLogisticsTimeline($status) {
    $stages = [
        ['key' => 'pending', 'label' => 'Placed 📝', 'num' => 0],
        ['key' => 'accepted', 'label' => 'Accepted 🤝', 'num' => 1],
        ['key' => 'packed', 'label' => 'Packed 📦', 'num' => 2],
        ['key' => 'shipped', 'label' => 'Shipped 🚚', 'num' => 3],
        ['key' => 'delivered', 'label' => 'Delivered 🤝', 'num' => 4]
    ];
    
    $status = strtolower($status);
    
    if ($status === 'cancelled') {
        echo '<div class="timeline-wrapper">';
        echo '<div class="timeline-title">Logistics Status Timeline</div>';
        echo '<div style="background: rgba(239, 68, 68, 0.08); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-sm); padding: 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">';
        echo '❌ Order Cancelled & Items Returned to Stock';
        echo '</div>';
        echo '</div>';
        return;
    }
    
    // Map active stage indices
    $active_idx = 0;
    foreach ($stages as $idx => $stage) {
        if ($stage['key'] === $status) {
            $active_idx = $idx;
            break;
        }
    }
    
    // Determine progress bar width percentage (4 segments: 0%, 25%, 50%, 75%, 100%)
    $progress_pct = $active_idx * 25;
    
    echo '<div class="timeline-wrapper">';
    echo '<div class="timeline-title">Logistics Status Timeline</div>';
    echo '<div class="timeline-path">';
    echo '<div class="timeline-progress-bar" style="width: ' . $progress_pct . '%;"></div>';
    
    foreach ($stages as $idx => $stage) {
        $node_class = '';
        if ($idx < $active_idx) {
            $node_class = 'completed';
        } else if ($idx == $active_idx) {
            $node_class = 'active';
        }
        
        echo '<div class="timeline-node ' . $node_class . '">';
        echo '<div class="timeline-node-circle">' . ($idx + 1) . '</div>';
        echo '<div class="timeline-node-label">' . $stage['label'] . '</div>';
        echo '</div>';
    }
    
    echo '</div>';
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
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <header class="navbar">
        <a href="marketplace.php" class="navbar-brand">
            <span>🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <a href="marketplace.php" style="color: var(--text-muted); font-weight: 600;">Marketplace</a>
            <a href="my_orders.php" style="color: var(--secondary); font-weight: 700;">My Orders</a>
            <a href="../market_prices.php" style="color: var(--text-muted); font-weight: 600;">Live Prices</a>
            <a href="../admin_complaints.php" style="color: #ef4444; font-weight: 700;">🛡️ Dispute Admin</a>
            
            <!-- Glowing Notification Bell -->
            <div class="notif-bell-container" id="notif-bell-btn">
                <span class="notif-bell-icon">🔔</span>
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
                                $unread_class = $notif['is_read'] == 0 ? 'unread' : '';
                                echo '<div class="notif-item ' . $unread_class . '">';
                                echo '<div class="notif-item-text">' . htmlspecialchars($notif['message']) . '</div>';
                                echo '<div class="notif-item-time">' . date("d M, h:i A", strtotime($notif['created_at'])) . '</div>';
                                echo '</div>';
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
                <span>🛒</span> <?php echo htmlspecialchars($_SESSION['name']); ?> (Buyer)
            </div>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <!-- Content -->
    <div class="grid-container animate-fade">
        
        <?php if($show_success) { ?>
            <div style="background: var(--success-light); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--primary-hover); padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">🎉</span>
                <div>
                    <h4 style="color: var(--primary-hover); margin: 0;">Order Placed Successfully!</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Your request has been sent directly to the farmer. Check status updates below.</p>
                </div>
            </div>
        <?php } ?>

        <?php if($show_cancelled) { ?>
            <div style="background: var(--danger-light); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">❌</span>
                <div>
                    <h4 style="color: #ef4444; margin: 0;">Order Cancelled!</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Order status has been updated to Cancelled. Crop inventory stock has been restored.</p>
                </div>
            </div>
        <?php } ?>

        <?php if($show_error) { ?>
            <div style="background: var(--warning-light); border: 1px solid rgba(245, 158, 11, 0.2); color: #d97706; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">⚠️</span>
                <div>
                    <h4 style="color: #d97706; margin: 0;">Operation Warning</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Unable to cancel order. The shipment may already be accepted, processed or shipped by the grower.</p>
                </div>
            </div>
        <?php } ?>

        <?php if(isset($_GET['payment_success'])) { ?>
            <div style="background: var(--success-light); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--primary-hover); padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">💳</span>
                <div>
                    <h4 style="color: var(--primary-hover); margin: 0;">Payment Submitted Successfully!</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">The transaction reference ID has been recorded. The grower has been notified to verify and ship.</p>
                </div>
            </div>
        <?php } ?>

        <?php if(isset($_GET['err']) && $_GET['err'] == 'empty_txn') { ?>
            <div style="background: var(--warning-light); border: 1px solid rgba(245, 158, 11, 0.2); color: #d97706; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">⚠️</span>
                <div>
                    <h4 style="color: #d97706; margin: 0;">Missing Transaction ID</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Please enter a valid transaction reference ID to log your UPI settlement.</p>
                </div>
            </div>
        <?php } ?>

        <?php if(isset($_GET['err']) && $_GET['err'] == 'payment_failed') { ?>
            <div style="background: var(--danger-light); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">❌</span>
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
            
            <div class="grid-3">
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
                ?>
                    <div class="glass-card animate-slide">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <span class="badge <?php echo $badge_class; ?>">
                                    🏷️ <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                                <?php if ($row['is_paid'] == 1): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700;">
                                        💳 Paid & Settled
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">
                                Order #<?php echo $row['id']; ?>
                            </span>
                        </div>
                        
                        <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 6px;">
                            🌾 <?php echo htmlspecialchars($row['crop_name']); ?>
                        </h3>
                        
                        <div style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 14px;">
                            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                                <span style="color: var(--text-muted);">Quantity ordered:</span>
                                <span style="font-weight: 700; color: var(--dark);"><?php echo $qty; ?> kg</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                                <span style="color: var(--text-muted);">Unit Price:</span>
                                <span style="font-weight: 600; color: var(--dark);">₹<?php echo $price; ?> / kg</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                                <span style="color: var(--text-muted);">Transport Cost:</span>
                                <span style="font-weight: 600; color: #f59e0b;">+ ₹<?php echo $transport_cost; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                                <span style="color: var(--text-muted);">Total Cost:</span>
                                <span style="font-weight: 700; color: var(--secondary);">₹<?php echo number_format($total); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                                <span style="color: var(--text-muted);">Payment Status:</span>
                                <?php if ($row['is_paid'] == 1): ?>
                                    <span style="font-weight: 700; color: #10b981;">
                                        🟢 Settled (Ref: <?php echo htmlspecialchars($row['payment_txn']); ?>)
                                    </span>
                                <?php else: ?>
                                    <span style="font-weight: 700; color: #ef4444;">
                                        🔴 Unpaid - Action Needed
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 14px; border-top: 1px dashed rgba(0,0,0,0.05); padding-top: 4px; margin-top: 4px;">
                                <span style="color: var(--text-muted);">Fulfillment Est:</span>
                                <span style="font-weight: 700; color: var(--primary-hover);">🚀 2 Days Domestic</span>
                            </div>
                            
                            <!-- AgriDirect: Show Delivery OTP -->
                            <?php if (!empty($row['delivery_otp'])): ?>
                            <div style="background: rgba(245, 158, 11, 0.1); border: 1px dashed rgba(245, 158, 11, 0.4); padding: 8px; border-radius: var(--radius-sm); margin-top: 10px; text-align: center;">
                                <span style="font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px;">Delivery Verification OTP</span>
                                <span style="font-size: 18px; font-weight: 800; letter-spacing: 2px; color: #d97706;"><?php echo htmlspecialchars($row['delivery_otp']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- AgriDirect: Live Tracking Status -->
                            <?php if (!empty($row['tracking_status'])): ?>
                            <div style="margin-top: 10px; padding: 8px; background: var(--light-bg); border-radius: var(--radius-sm); font-size: 13px;">
                                <span style="color: var(--text-muted);">Live Delivery Status:</span>
                                <span style="font-weight: 700; color: var(--secondary); float: right;">🚚 <?php echo htmlspecialchars($row['tracking_status']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Logistics status timeline -->
                        <?php renderLogisticsTimeline($row['status']); ?>

                        <!-- Collapsible UPI Payment Drawer -->
                        <?php if ($row['is_paid'] == 0 && $status !== 'cancelled'): ?>
                            <div class="payment-drawer-container" style="border: 1px solid rgba(99, 102, 241, 0.2); border-radius: var(--radius-md); background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(168, 85, 247, 0.03) 100%); margin-top: 14px; padding: 12px; overflow: hidden; transition: all 0.3s ease;">
                                <div class="payment-drawer-header" onclick="togglePaymentDrawer(<?php echo $row['id']; ?>)" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">
                                    <span style="font-size: 13px; font-weight: 700; color: #4f46e5; display: flex; align-items: center; gap: 6px;">
                                        💳 Settle Instantly via UPI QR
                                    </span>
                                    <span id="drawer-arrow-<?php echo $row['id']; ?>" style="font-size: 12px; transition: transform 0.3s; transform: rotate(0deg); color: #4f46e5;">▼</span>
                                </div>
                                
                                <div id="drawer-body-<?php echo $row['id']; ?>" class="payment-drawer-body" style="display: none; margin-top: 12px; text-align: center; border-top: 1px dashed rgba(99, 102, 241, 0.15); padding-top: 12px;">
                                    <div style="background: white; border: 1px solid var(--border); padding: 12px; border-radius: var(--radius-sm); display: inline-block; box-shadow: 0 4px 10px rgba(0,0,0,0.03); margin-bottom: 8px;">
                                        <?php
                                        // AgriDirect: Real Farmer UPI Payment
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
                                        <img src="<?php echo $qr_api; ?>" alt="UPI QR Code" style="width: 150px; height: 150px; display: block; margin: 0 auto;">
                                    </div>
                                    <p style="font-size: 11px; color: var(--text-muted); margin-bottom: 12px; max-width: 250px; margin-left: auto; margin-right: auto; line-height: 1.4;">
                                        Scan this dynamic QR with any UPI app (GPay, PhonePe, Paytm) to pay <strong><?php echo htmlspecialchars($row['farmer_name']); ?></strong> the exact amount: <strong>₹<?php echo number_format($total); ?></strong>.
                                    </p>
                                    
                                    <form action="my_orders.php" method="POST" style="margin-top: 8px;">
                                        <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                        <div style="text-align: left; margin-bottom: 10px;">
                                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 4px;">UPI Transaction Ref ID</label>
                                            <input type="text" name="txn_id" placeholder="12-digit transaction ID" required 
                                                   style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); font-family: inherit; font-size: 13px; background: rgba(255,255,255,0.9); box-sizing: border-box;"
                                                   onfocus="this.style.borderColor='#4f46e5'; this.style.boxShadow='0 0 0 2px rgba(99, 102, 241, 0.2)'" 
                                                   onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
                                        </div>
                                        <button type="submit" name="submit_payment" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 10px; font-size: 13px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border: none;">
                                            Verify & Mark Paid 💳
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($row['is_paid'] == 1): ?>
                            <div style="border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-md); background: linear-gradient(135deg, rgba(16, 185, 129, 0.03) 0%, rgba(52, 211, 153, 0.03) 100%); margin-top: 14px; padding: 12px; text-align: center;">
                                <div style="font-size: 14px; font-weight: 700; color: #10b981; display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 4px;">
                                    <span>🛡️</span> UPI Paid & Settled
                                </div>
                                <span style="font-size: 11px; color: var(--text-muted); display: block;">
                                    Txn Reference ID: <strong><?php echo htmlspecialchars($row['payment_txn']); ?></strong>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- QR Delivery Handover Validation -->
                        <?php if ($status !== 'delivered' && $status !== 'cancelled'): ?>
                            <div style="border-top: 1px dashed var(--border); padding-top: 12px; margin-top: 12px; text-align: center;">
                                <span style="font-size: 11px; font-weight: 700; color: var(--secondary); display: block; margin-bottom: 6px; text-transform: uppercase;">🤝 Delivery Handover QR Code</span>
                                <?php
                                $verify_url = "http://localhost/farm_portal/farmer/verify_delivery.php?order_id=" . $row['id'];
                                $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verify_url);
                                ?>
                                <a href="<?php echo $verify_url; ?>" style="display: inline-block; cursor: pointer; border: 1px solid var(--border); padding: 8px; border-radius: var(--radius-sm); background: white;" title="Click to Emulate Scan">
                                    <img src="<?php echo $qr_api; ?>" alt="Verification QR" style="width: 100px; height: 100px; display: block; margin: 0 auto;">
                                </a>
                                <span style="font-size: 10px; color: var(--text-muted); display: block; margin-top: 4px;">Farmer can scan this QR or click to verify handover instantly! (Click to Emulate Mobile Scan 📱)</span>
                            </div>
                        <?php endif; ?>

                        <!-- Cancellation Trigger Button -->
                        <?php if ($status === 'pending'): ?>
                            <form action="my_orders.php" method="POST" style="margin-top: 12px;" onsubmit="return confirm('Are you sure you want to cancel this order? It will return crop stock instantly.');">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="cancel_order" class="btn btn-danger" style="width: 100%; justify-content: center; padding: 10px; font-size: 12.5px;">
                                    ❌ Cancel Order & Restock
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Connection info of the Farmer -->
                        <div style="background: var(--light-bg); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 8px;">
                            <h4 style="font-size: 13px; color: var(--secondary); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                                👨‍🌾 Grower Information
                            </h4>
                            <p style="font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 2px;">
                                <a href="../farmer/profile.php?id=<?php echo $row['farmer_id']; ?>" style="color: var(--secondary); text-decoration: underline;">
                                    <?php echo htmlspecialchars($row['farmer_name']); ?>
                                </a>
                            </p>
                            <p style="font-size: 12px; color: var(--text-muted);">
                                ✉️ <?php echo htmlspecialchars($row['farmer_email']); ?>
                            </p>
                        </div>
                        
                        <!-- Invoice PDF trigger link -->
                        <a class="btn btn-secondary" style="width: 100%; justify-content: center; padding: 10px; font-size: 12.5px; margin-top: 10px;" 
                           href="invoice.php?id=<?php echo $row['id']; ?>">
                            🖨️ View & Print Trade Invoice
                        </a>

                        <!-- AI Damage Verification Integration -->
                        <?php
                        $comp_check = mysqli_query($conn, "SELECT status, ai_result FROM complaints WHERE order_id = '".$row['id']."'");
                        $complaint_filed = ($comp_check && mysqli_num_rows($comp_check) > 0);
                        if ($complaint_filed) {
                            $complaint_row = mysqli_fetch_assoc($comp_check);
                            $c_status = strtolower($complaint_row['status']);
                            $status_color = "#f59e0b";
                            if ($c_status === 'approved') $status_color = "#10b981";
                            if ($c_status === 'rejected') $status_color = "#ef4444";
                        ?>
                            <div style="margin-top: 10px; padding: 12px; border-radius: var(--radius-sm); background: rgba(15, 23, 42, 0.03); border: 1px solid var(--border); font-size: 13px; text-align: left;">
                                <span style="font-weight: 700; color: var(--secondary); display: block; margin-bottom: 4px;">⚠️ Damage Claim Status</span>
                                <span style="font-size: 11.5px; color: var(--text-muted); display: block; margin-bottom: 4px;">AI Analysis: <strong style="color: #4f46e5;"><?php echo htmlspecialchars($complaint_row['ai_result']); ?></strong></span>
                                <span style="font-size: 12px; font-weight: 800; color: <?php echo $status_color; ?>; text-transform: uppercase;">● Status: <?php echo htmlspecialchars($complaint_row['status']); ?></span>
                            </div>
                        <?php } else if ($status === 'delivered') { ?>
                            <a class="btn btn-danger" style="width: 100%; justify-content: center; padding: 10px; font-size: 12.5px; margin-top: 10px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;" 
                               href="report_damage.php?order_id=<?php echo $row['id']; ?>">
                                ⚠️ Report Damage & File AI Claim
                            </a>
                        <?php } ?>
                        
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
                <a class="btn btn-primary" href="marketplace.php">Browse Marketplace 🌾</a>
            </div>

        <?php } ?>

    </div>

    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>
    <script>
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
