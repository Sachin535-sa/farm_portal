<?php
session_start();
include("../config/db.php");

// Session validation
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "farmer"){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Fetch notifications
$user_id_clean = mysqli_real_escape_string($conn, $farmer_id);
$notif_query = "SELECT * FROM notifications WHERE user_id = '$user_id_clean' ORDER BY created_at DESC LIMIT 5";
$notif_res = mysqli_query($conn, $notif_query);
$unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$user_id_clean' AND is_read = 0";
$unread_count_res = mysqli_query($conn, $unread_count_query);
$unread_count = 0;
if ($unread_count_res) {
    $unread_count_row = mysqli_fetch_assoc($unread_count_res);
    $unread_count = (int)$unread_count_row['count'];
}

// Handling Dispatch with Original Parcel Photo
if(isset($_POST['dispatch_order']) && isset($_FILES['original_parcel'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['dispatch_order_id']);
    
    // File upload
    $img_name = $_FILES['original_parcel']['name'];
    $tmp_name = $_FILES['original_parcel']['tmp_name'];
    $ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
    $new_img_name = "parcel_" . $order_id . "_" . time() . "." . $ext;
    
    // Ensure upload directory exists
    if (!file_exists("../uploads/parcels/")) {
        mkdir("../uploads/parcels/", 0777, true);
    }
    
    if (move_uploaded_file($tmp_name, "../uploads/parcels/" . $new_img_name)) {
        mysqli_begin_transaction($conn);
        try {
            $check_sql = "SELECT o.*, c.crop_name, c.farmer_id 
                          FROM orders o 
                          JOIN crops c ON o.crop_id = c.id 
                          WHERE o.id='$order_id' AND c.farmer_id='$farmer_id' 
                          FOR UPDATE";
            $check_res = mysqli_query($conn, $check_sql);
            if($check_res && mysqli_num_rows($check_res) > 0){
                $order_row = mysqli_fetch_assoc($check_res);
                $buyer_id = $order_row['buyer_id'];
                $crop_name = $order_row['crop_name'];
                $qty = $order_row['quantity'];
                
                // Update orders status to shipped and save parcel image
                mysqli_query($conn, "UPDATE orders SET status = 'shipped', tracking_status = 'Out for Delivery', original_parcel_image = '$new_img_name' WHERE id = '$order_id'");
                
                $msg = "Your order #$order_id for $qty kg of $crop_name has been SHIPPED and is out for delivery! Reference packaging image stored.";
                $msg_clean = mysqli_real_escape_string($conn, $msg);
                mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$msg_clean')");
                
                mysqli_commit($conn);
                header("Location: orders.php?success=updated");
                exit();
            } else {
                mysqli_rollback($conn);
                header("Location: orders.php?err=not_found");
                exit();
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            header("Location: orders.php?err=db_error");
            exit();
        }
    } else {
        header("Location: orders.php?err=upload_failed");
        exit();
    }
}

// Handling dynamic status updates from Farmer (Step-by-Step State Machine)
if(isset($_GET['update_id']) && isset($_GET['status'])){
    $order_id = mysqli_real_escape_string($conn, $_GET['update_id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    
    // Begin transaction for safety
    mysqli_begin_transaction($conn);
    try {
        // Fetch order details with locking for safety (verify ownership)
        $check_sql = "SELECT o.*, c.crop_name, c.farmer_id 
                      FROM orders o 
                      JOIN crops c ON o.crop_id = c.id 
                      WHERE o.id='$order_id' AND c.farmer_id='$farmer_id' 
                      FOR UPDATE";
        $check_res = mysqli_query($conn, $check_sql);
        
        if($check_res && mysqli_num_rows($check_res) > 0){
            $order_row = mysqli_fetch_assoc($check_res);
            $old_status = strtolower($order_row['status']);
            $buyer_id = $order_row['buyer_id'];
            $crop_id = $order_row['crop_id'];
            $crop_name = $order_row['crop_name'];
            $qty = (int)$order_row['quantity'];
            
            // Farmer-side Cancellation
            if ($status === 'cancelled' && $old_status === 'pending') {
                // Restore crop quantity back to stock
                mysqli_query($conn, "UPDATE crops SET quantity = quantity + $qty WHERE id = '$crop_id'");
                
                // Update status to cancelled
                mysqli_query($conn, "UPDATE orders SET status = 'cancelled' WHERE id = '$order_id'");
                
                // Notify the buyer
                $msg = "Order #$order_id for $qty kg of $crop_name has been cancelled by the grower. Stock has been returned to inventory.";
                $msg_clean = mysqli_real_escape_string($conn, $msg);
                mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$msg_clean')");
                
                mysqli_commit($conn);
                header("Location: orders.php?success=cancelled");
                exit();
            } else {
                // Verify step progression
                $valid = false;
                $msg = "";
                
                if ($status === 'accepted' && $old_status === 'pending') {
                    $valid = true;
                    $msg = "Your order #$order_id for $qty kg of $crop_name has been ACCEPTED by the grower and is now being packed.";
                    $track_status = 'Preparing';
                } else if ($status === 'packed' && $old_status === 'accepted') {
                    $valid = true;
                    $msg = "Your order #$order_id for $qty kg of $crop_name has been PACKED and is ready for dispatch!";
                    $track_status = 'Packed';
                } else if ($status === 'shipped' && $old_status === 'packed') {
                    $valid = true;
                    $msg = "Your order #$order_id for $qty kg of $crop_name has been SHIPPED and is out for delivery! Handover QR code is ready.";
                    $track_status = 'Out for Delivery';
                }
                
                if ($valid) {
                    mysqli_query($conn, "UPDATE orders SET status = '$status', tracking_status = '$track_status' WHERE id = '$order_id'");
                    
                    // Insert buyer notification
                    $msg_clean = mysqli_real_escape_string($conn, $msg);
                    mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$msg_clean')");
                    
                    mysqli_commit($conn);
                    header("Location: orders.php?success=updated");
                    exit();
                } else {
                    mysqli_rollback($conn);
                    header("Location: orders.php?err=invalid_progression");
                    exit();
                }
            }
        } else {
            mysqli_rollback($conn);
            header("Location: orders.php?err=not_found");
            exit();
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: orders.php?err=db_error");
        exit();
    }
}

// Fetch all orders placed for this Farmer's crops
$sql = "SELECT o.*, c.crop_name, o.price, u.name as buyer_name, u.email as buyer_email 
        FROM orders o
        JOIN crops c ON o.crop_id = c.id
        JOIN users u ON o.buyer_id = u.id
        WHERE c.farmer_id = '$farmer_id'
        ORDER BY o.id DESC";

$result = mysqli_query($conn, $sql);

// Status alert checks
$show_updated = isset($_GET['success']) && $_GET['success'] === 'updated';
$show_cancelled = isset($_GET['success']) && $_GET['success'] === 'cancelled';
$show_error = isset($_GET['err']);

// Logistics progress render helper (Farmer version matches Buyer design perfectly)
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
    <title>Manage Orders | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css?v=1.6">
</head>
<body>

    <!-- Header bar -->
    <header class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span>🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <a href="dashboard.php" style="color: var(--text-muted); font-weight: 600;">My Listings</a>
            <a href="orders.php" style="color: var(--secondary); font-weight: 700;">Manage Orders</a>
            <a href="../market_prices.php" style="color: var(--text-muted); font-weight: 600;">Live Prices</a>
            <a href="../admin_complaints.php" style="color: #ef4444; font-weight: 700;">🛡️ Dispute Admin</a>
            <a href="profile.php?id=<?php echo $farmer_id; ?>" style="color: var(--text-muted); font-weight: 600;">My Public Portfolio</a>
            
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
                <span>👨‍🌾</span> <?php echo htmlspecialchars($_SESSION['name']); ?>
            </div>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <!-- Content container -->
    <div class="grid-container animate-fade">
        
        <?php if($show_updated) { ?>
            <div style="background: var(--success-light); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--primary-hover); padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">📈</span>
                <div>
                    <h4 style="color: var(--primary-hover); margin: 0;">Logistics Pipeline Updated</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Order status has been updated and a status notification has been delivered to the buyer.</p>
                </div>
            </div>
        <?php } ?>

        <?php if($show_cancelled) { ?>
            <div style="background: var(--danger-light); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">❌</span>
                <div>
                    <h4 style="color: #ef4444; margin: 0;">Order Cancelled</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Order status has been updated to Cancelled. Crop inventory stock has been restored.</p>
                </div>
            </div>
        <?php } ?>

        <?php if($show_error) { ?>
            <div style="background: var(--warning-light); border: 1px solid rgba(245, 158, 11, 0.2); color: #d97706; padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">⚠️</span>
                <div>
                    <h4 style="color: #d97706; margin: 0;">Logistics Operation Error</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">
                        <?php 
                        $err = $_GET['err'];
                        if ($err === 'invalid_progression') echo 'Invalid stage progression. Please follow step-by-step dispatch logistics.';
                        elseif ($err === 'not_found') echo 'Order or crop record not found or unauthorized access.';
                        else echo 'Database or system transaction lock occurred. Please try again.';
                        ?>
                    </p>
                </div>
            </div>
        <?php } ?>

        <div style="margin-bottom: 32px;">
            <h1 style="font-size: 32px; color: var(--dark);">Manage Buyer Orders</h1>
            <p style="color: var(--text-muted);">Process pending requests, view buyer details for deliveries, and update dispatch statuses</p>
        </div>

        <?php if(mysqli_num_rows($result) > 0) { ?>
            
            <div class="grid-3">
                <?php while($row = mysqli_fetch_assoc($result)) { 
                    $qty = (int)$row['quantity'];
                    $price = (int)$row['price'];
                    $total = $qty * $price;
                    $status = strtolower($row['status']);
                    
                    // Assign class to status badge
                    $badge_class = "badge-pending";
                    if ($status == "shipped") $badge_class = "badge-shipped";
                    if ($status == "delivered") $badge_class = "badge-delivered";
                    if ($status == "cancelled") $badge_class = "badge-cancelled";
                ?>
                    <div class="glass-card animate-slide">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span class="badge <?php echo $badge_class; ?>">
                                🏷️ <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">
                                Order #<?php echo $row['id']; ?>
                            </span>
                        </div>
                        
                        <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 6px;">
                            🌾 <?php echo htmlspecialchars($row['crop_name']); ?>
                        </h3>
                        
                        <div style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 14px;">
                            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                                <span style="color: var(--text-muted);">Quantity:</span>
                                <span style="font-weight: 700; color: var(--dark);"><?php echo $qty; ?> kg</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 14px;">
                                <span style="color: var(--text-muted);">Revenue:</span>
                                <span style="font-weight: 700; color: var(--secondary);">₹<?php echo number_format($total); ?></span>
                            </div>
                        </div>
                        
                        <!-- Render the logistics timeline graphic -->
                        <?php renderLogisticsTimeline($row['status']); ?>
                        
                        <!-- Buyer connection details panel -->
                        <div style="background: var(--light-bg); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 20px;">
                            <h4 style="font-size: 13px; color: var(--secondary); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                                👤 Buyer Connection Details
                            </h4>
                            <p style="font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 2px;">
                                <?php echo htmlspecialchars($row['buyer_name']); ?>
                            </p>
                            <p style="font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 4px;">
                                ✉️ <?php echo htmlspecialchars($row['buyer_email']); ?>
                            </p>
                        </div>
                        
                        <!-- Dynamic State-machine Operations -->
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php if($status == "pending") { ?>
                                <div style="display: flex; gap: 8px; width: 100%;">
                                    <a class="btn btn-primary" style="flex: 2; justify-content: center; padding: 10px; font-size: 12.5px;" 
                                       href="orders.php?update_id=<?php echo $row['id']; ?>&status=accepted">
                                        🤝 Accept Order
                                    </a>
                                    <a class="btn btn-danger" style="flex: 1; justify-content: center; padding: 10px; font-size: 12.5px;" 
                                       onclick="return confirm('Are you sure you want to cancel and restock this order?');"
                                       href="orders.php?update_id=<?php echo $row['id']; ?>&status=cancelled">
                                        ❌ Cancel
                                    </a>
                                </div>
                            <?php } else if($status == "accepted") { ?>
                                <a class="btn" style="width: 100%; justify-content: center; padding: 10px; font-size: 13px; background: #0f766e; color: white;" 
                                   href="orders.php?update_id=<?php echo $row['id']; ?>&status=packed">
                                    📦 Mark Packed
                                </a>
                            <?php } else if($status == "packed") { ?>
                                <form action="orders.php" method="POST" enctype="multipart/form-data" style="margin-top: 10px; padding: 12px; border: 1px dashed rgba(16, 185, 129, 0.4); border-radius: var(--radius-sm); background: rgba(16, 185, 129, 0.03); text-align: left;">
                                    <span style="font-size: 11.5px; font-weight: 700; color: var(--secondary); display: block; margin-bottom: 6px; text-transform: uppercase;">📸 Original Packaging Image</span>
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <input type="file" name="original_parcel" accept="image/*" required style="font-size: 11.5px; width: 100%;">
                                    </div>
                                    <input type="hidden" name="dispatch_order_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="dispatch_order" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 8px; font-size: 12.5px;">
                                        🚚 Upload & Dispatch Order
                                    </button>
                                </form>
                            <?php } else if($status == "shipped") { ?>
                                <div style="border: 1px dashed var(--border); border-radius: var(--radius-sm); padding: 10px; background: var(--light-bg); text-align: center;">
                                    <span style="font-size: 11px; font-weight: 700; color: var(--secondary); display: block; margin-bottom: 4px;">🤝 PENDING HANDOVER VERIFICATION</span>
                                    <a class="btn" style="width: 100%; justify-content: center; padding: 10px; font-size: 13px; background: #3b82f6; color: white;" 
                                       href="verify_delivery.php?order_id=<?php echo $row['id']; ?>">
                                        🔑 Verify Handover (Confirm Delivery)
                                    </a>
                                </div>
                            <?php } else if($status == "delivered") { ?>
                                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--primary-hover); font-weight: 700; font-size: 14px; padding: 10px; background: var(--success-light); border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.15);">
                                    <span>🎉</span> Trade Handover Settled
                                </div>
                            <?php } else { ?>
                                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--danger); font-weight: 700; font-size: 14px; padding: 10px; background: var(--danger-light); border-radius: var(--radius-sm); border: 1px solid rgba(239, 68, 68, 0.15);">
                                    <span>❌</span> Order Cancelled
                                </div>
                            <?php } ?>
                            
                            <!-- Buyer Chat link -->
                            <a class="btn btn-secondary" style="width: 100%; justify-content: center; padding: 10px; font-size: 12.5px;" 
                               href="../chat.php?farmer_id=<?php echo $farmer_id; ?>&buyer_id=<?php echo $row['buyer_id']; ?>&crop_id=<?php echo $row['crop_id']; ?>">
                                💬 Negotiate / Chat with Buyer
                            </a>
                        </div>
                        
                    </div>
                <?php } ?>
            </div>

        <?php } else { ?>
            
            <div class="empty-state animate-slide">
                <div class="empty-state-icon">📭</div>
                <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 8px;">No Orders Received Yet</h3>
                <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;">
                    Once buyers view your crop listings and purchase them, the requests will appear here with their contact details. Keep your stock updated!
                </p>
            </div>

        <?php } ?>

    </div>

    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>

</body>
</html>
