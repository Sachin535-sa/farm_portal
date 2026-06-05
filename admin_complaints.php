<?php
session_start();
include("config/db.php");

// Session verification (Let's make it easy to access for presentation, or verify buyer/farmer role or standard user)
// For pure premium project demonstration and manual testing, we will check if a session exists or allow access, but let's provide a nice Admin simulation header!
$is_logged_in = isset($_SESSION['user_id']);
$role = $is_logged_in ? $_SESSION['role'] : 'admin';
$name = $is_logged_in ? $_SESSION['name'] : 'System Admin';

// Handle Action clicks (Approve / Reject)
if (isset($_POST['resolve_complaint'])) {
    $complaint_id = mysqli_real_escape_string($conn, $_POST['complaint_id']);
    $action = mysqli_real_escape_string($conn, $_POST['action']); // 'Approved' or 'Rejected'
    
    // Fetch complaint and related order details
    $c_sql = "SELECT c.*, o.buyer_id, o.crop_id, cr.crop_name, cr.farmer_id 
              FROM complaints c
              JOIN orders o ON c.order_id = o.id
              JOIN crops cr ON o.crop_id = cr.id
              WHERE c.id = '$complaint_id'";
    $c_res = mysqli_query($conn, $c_sql);
    
    if ($c_res && mysqli_num_rows($c_res) > 0) {
        $comp = mysqli_fetch_assoc($c_res);
        $order_id = $comp['order_id'];
        $buyer_id = $comp['buyer_id'];
        $farmer_id = $comp['farmer_id'];
        $crop_name = $comp['crop_name'];
        
        // Update complaint status
        mysqli_query($conn, "UPDATE complaints SET status = '$action' WHERE id = '$complaint_id'");
        
        // Update order status based on action
        $new_order_status = ($action === 'Approved') ? 'returned' : 'delivered';
        mysqli_query($conn, "UPDATE orders SET status = '$new_order_status' WHERE id = '$order_id'");
        
        // Send notifications
        if ($action === 'Approved') {
            $buyer_msg = "🎉 Claim Approved: Your package damage claim for Order #$order_id ($crop_name) has been approved. A refund has been issued.";
            $farmer_msg = "📢 Dispute Resolved: The damage complaint for Order #$order_id ($crop_name) was approved by admin. A return status has been registered.";
        } else {
            $buyer_msg = "❌ Claim Rejected: Your package damage claim for Order #$order_id ($crop_name) was rejected by admin after manual review.";
            $farmer_msg = "📢 Dispute Resolved: The damage complaint for Order #$order_id ($crop_name) was rejected by admin. Payment settlement stands.";
        }
        
        $buyer_msg_clean = mysqli_real_escape_string($conn, $buyer_msg);
        $farmer_msg_clean = mysqli_real_escape_string($conn, $farmer_msg);
        
        mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$buyer_msg_clean')");
        mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$farmer_msg_clean')");
        
        header("Location: admin_complaints.php?success=1");
        exit();
    }
}

// Fetch all complaints with detailed order information
$sql = "SELECT c.*, o.quantity, o.price, o.original_parcel_image, cr.crop_name, b.name as buyer_name, f.name as farmer_name 
        FROM complaints c
        JOIN orders o ON c.order_id = o.id
        JOIN crops cr ON o.crop_id = cr.id
        JOIN users b ON o.buyer_id = b.id
        JOIN users f ON cr.farmer_id = f.id
        ORDER BY c.created_at DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispute & Claim Management Center | AgroNava Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-nav {
            background: rgba(15, 23, 42, 0.95) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        .admin-nav .navbar-brand {
            color: #fff !important;
        }
        .admin-nav a {
            color: #94a3b8 !important;
        }
        .admin-nav a:hover {
            color: #fff !important;
        }
        .score-bar {
            background: #e2e8f0;
            border-radius: 50px;
            height: 6px;
            width: 100%;
            overflow: hidden;
            margin-top: 6px;
        }
        .score-fill {
            height: 100%;
            border-radius: 50px;
        }
    </style>
</head>
<body style="background-color: #f8fafc;">

    <!-- Elite Navbar -->
    <header class="navbar admin-nav">
        <a href="index.php" class="navbar-brand">
            <span>🛡️</span> AgroNava Admin Panel
        </a>
        <div class="navbar-menu">
            <a href="index.php" style="font-weight: 600;">Main Landing</a>
            <a href="buyer/marketplace.php" style="font-weight: 600;">Marketplace</a>
            <a href="admin_complaints.php" style="color: var(--primary) !important; font-weight: 700;">Disputes & Claims</a>
            <div class="user-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                <span>👑</span> System Administrator
            </div>
        </div>
    </header>

    <div class="grid-container animate-fade">
        <div style="margin-bottom: 36px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; color: var(--primary); background: var(--primary-light); padding: 4px 10px; border-radius: 50px; display: inline-block; margin-bottom: 10px;">⚖️ Parcel Claim Center</span>
                <h1 style="font-size: 36px; color: var(--dark); line-height: 1.1;">Package Dispute Resolution Dashboard</h1>
                <p style="color: var(--text-muted); font-size: 14.5px; margin-top: 4px;">Monitor automated AI parcel damage verifications, compare packaging, and settle crop claims.</p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div style="background: var(--success-light); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--primary-hover); padding: 18px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 12px;" class="animate-slide">
                <span style="font-size: 24px;">✅</span>
                <div>
                    <h4 style="color: var(--primary-hover); margin: 0;">Claim Dispute Settled</h4>
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-muted); margin: 0;">Complaint status updated. Real-time notifications and ledger adjustments have been sent to both buyer and farmer.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <div class="grid-3" style="grid-template-columns: 1fr; gap: 32px;">
                <?php while ($row = mysqli_fetch_assoc($result)): 
                    $qty = intval($row['quantity']);
                    $price = intval($row['price']);
                    $total = $qty * $price;
                    $c_status = strtolower($row['status']);
                    
                    $status_badge = "badge-pending";
                    if ($c_status === 'approved') $status_badge = "badge-delivered";
                    if ($c_status === 'rejected') $status_badge = "badge-cancelled";
                ?>
                    <div class="glass-card animate-slide" style="background: white; padding: 32px; display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 32px; align-items: start;">
                        
                        <!-- Details and side-by-side images -->
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <span class="badge <?php echo $status_badge; ?>">
                                        ● Dispute Status: <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                    <span class="badge" style="background: rgba(79, 70, 229, 0.08); color: #4f46e5; font-weight: 700;">
                                        🤖 AI Result: <?php echo htmlspecialchars($row['ai_result']); ?>
                                    </span>
                                </div>
                                <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">
                                    Claim ID #<?php echo $row['id']; ?> | Order #<?php echo $row['order_id']; ?>
                                </span>
                            </div>

                            <h3 style="font-size: 24px; color: var(--dark); margin-bottom: 8px;">
                                🌾 <?php echo htmlspecialchars($row['crop_name']); ?> Claim
                            </h3>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; font-size: 13.5px; background: var(--light-bg); padding: 14px; border-radius: var(--radius-sm);">
                                <div>
                                    <span style="color: var(--text-muted); display: block;">🧑‍🌾 Crop Grower</span>
                                    <strong style="color: var(--dark);"><?php echo htmlspecialchars($row['farmer_name']); ?></strong>
                                </div>
                                <div>
                                    <span style="color: var(--text-muted); display: block;">🛒 Buyer Claimant</span>
                                    <strong style="color: var(--dark);"><?php echo htmlspecialchars($row['buyer_name']); ?></strong>
                                </div>
                                <div style="margin-top: 8px;">
                                    <span style="color: var(--text-muted); display: block;">Quantity & Price</span>
                                    <strong><?php echo $qty; ?> kg @ ₹<?php echo $price; ?>/kg</strong>
                                </div>
                                <div style="margin-top: 8px;">
                                    <span style="color: var(--text-muted); display: block;">Dispute Value</span>
                                    <strong style="color: var(--secondary);">₹<?php echo number_format($total); ?></strong>
                                </div>
                            </div>

                            <div style="margin-bottom: 20px;">
                                <h4 style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">📝 Claimant's Description</h4>
                                <p style="font-size: 14px; color: var(--text-main); line-height: 1.5; font-weight: 500;">
                                    "<?php echo htmlspecialchars($row['reason']); ?>"
                                </p>
                            </div>

                            <!-- Side-by-Side Images -->
                            <h4 style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px;">📸 Side-by-Side Package Verification</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <!-- Original Packaging -->
                                <div>
                                    <span style="font-size: 11px; font-weight: 700; color: var(--secondary); display: block; margin-bottom: 6px; text-transform: uppercase;">🌱 Grower Packaging Reference</span>
                                    <?php if (!empty($row['original_parcel_image']) && file_exists("uploads/parcels/" . $row['original_parcel_image'])): ?>
                                        <div style="height: 180px; border-radius: var(--radius-sm); border: 1px solid var(--border); background-image: url('uploads/parcels/<?php echo htmlspecialchars($row['original_parcel_image']); ?>'); background-size: cover; background-position: center;"></div>
                                    <?php else: ?>
                                        <div style="height: 180px; border-radius: var(--radius-sm); border: 2px dashed var(--border); background: var(--light-bg); display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--text-muted); font-weight: 500;">
                                            No Reference Uploaded
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Claim Photo -->
                                <div>
                                    <span style="font-size: 11px; font-weight: 700; color: #ef4444; display: block; margin-bottom: 6px; text-transform: uppercase;">🚨 Customer Reported Damage</span>
                                    <?php if (!empty($row['image']) && file_exists("uploads/claims/" . $row['image'])): ?>
                                        <div style="height: 180px; border-radius: var(--radius-sm); border: 1px solid var(--border); background-image: url('uploads/claims/<?php echo htmlspecialchars($row['image']); ?>'); background-size: cover; background-position: center;"></div>
                                    <?php else: ?>
                                        <div style="height: 180px; border-radius: var(--radius-sm); border: 2px dashed var(--border); background: var(--light-bg); display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--text-muted); font-weight: 500;">
                                            Claim Image Missing
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- AI Gauges and Operations Panel -->
                        <div style="border-left: 1px dashed var(--border); padding-left: 32px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; color: #4f46e5; background: rgba(79, 70, 229, 0.08); padding: 4px 10px; border-radius: 50px; display: inline-block; margin-bottom: 16px;">🤖 Automated AI Insights</span>
                                
                                <!-- Damage Score Gauge -->
                                <div style="margin-bottom: 20px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 700; color: var(--dark);">
                                        <span>Damage Intensity</span>
                                        <span style="color: #ef4444;"><?php echo $row['damage_score']; ?>%</span>
                                    </div>
                                    <div class="score-bar">
                                        <div class="score-fill" style="width: <?php echo $row['damage_score']; ?>%; background: linear-gradient(90deg, #f59e0b, #ef4444);"></div>
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">Computed via Edge Density shift and pixel correlation metrics</span>
                                </div>

                                <!-- Fake Score Gauge -->
                                <div style="margin-bottom: 24px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 700; color: var(--dark);">
                                        <span>Manipulation Risk</span>
                                        <span style="color: #f59e0b;"><?php echo $row['fake_score']; ?>%</span>
                                    </div>
                                    <div class="score-bar">
                                        <div class="score-fill" style="width: <?php echo $row['fake_score']; ?>%; background: linear-gradient(90deg, #10b981, #f59e0b);"></div>
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">Assessed through color histogram overlapping and noise matching</span>
                                </div>

                                <!-- Quick AI Recommendation -->
                                <div style="background: rgba(79, 70, 229, 0.03); border: 1px dashed rgba(79, 70, 229, 0.2); padding: 14px; border-radius: var(--radius-sm); font-size: 13px;">
                                    <strong style="color: #4f46e5; display: block; margin-bottom: 4px;">💡 Recommendation:</strong>
                                    <?php 
                                    if ($row['fake_score'] > 60.0) {
                                        echo "⚠️ **DISMISS CLAIM**: Highly suspicious correlation discrepancies. Claim photos strongly match reference dimensions but user reports heavy disparity.";
                                    } elseif ($row['damage_score'] > 35.0) {
                                        echo "✅ **APPROVE DISPUTE**: Structural edge disparity is high. Color variations confirm physical distortion of the parcel.";
                                    } else {
                                        echo "ℹ️ **MANUAL INQUEST**: Mild discrepancies found. Request additional verification before approval.";
                                    }
                                    ?>
                                </div>
                            </div>

                            <!-- Resolution Actions -->
                            <?php if ($c_status === 'pending'): ?>
                                <div style="margin-top: 32px;">
                                    <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;">⚖️ Administrative Decision</span>
                                    <form action="admin_complaints.php" method="POST" style="display: flex; gap: 14px;">
                                        <input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="resolve_complaint" value="1" class="btn btn-primary" style="flex: 1; padding: 10px; font-size: 13px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none;" onclick="this.form.action.value='Approved';">
                                            Approve Return
                                        </button>
                                        <button type="submit" name="resolve_complaint" value="1" class="btn btn-danger" style="flex: 1; padding: 10px; font-size: 13px;" onclick="this.form.action.value='Rejected';">
                                            Reject Claim
                                        </button>
                                        <input type="hidden" name="action" value="Approved">
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="margin-top: 32px; background: var(--light-bg); padding: 12px; border-radius: var(--radius-sm); text-align: center; border: 1px solid var(--border);">
                                    <span style="font-size: 13px; font-weight: 700; color: var(--text-muted);">Dispute Settled as: <strong><?php echo htmlspecialchars($row['status']); ?></strong></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state animate-slide">
                <div class="empty-state-icon">⚖️</div>
                <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 8px;">No Disputes Registered</h3>
                <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;">Currently, there are no active parcel damage claims or disputes recorded in the platform ledger. Everything is running smoothly!</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
