<?php
session_start();
include("../config/db.php");

// Session check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer") {
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

if (!isset($_GET['order_id'])) {
    header("Location: my_orders.php");
    exit();
}

$order_id = mysqli_real_escape_string($conn, $_GET['order_id']);

// Fetch order traceability details
$order_query = mysqli_query($conn, "SELECT o.*, c.crop_name, c.harvest_date, c.quality_grade, c.is_organic,
                                            f.name as farmer_name, f.mobile_no as farmer_phone, f.address as farmer_address,
                                            d.name as driver_name, d.mobile_no as driver_phone
                                    FROM orders o 
                                    JOIN crops c ON o.crop_id = c.id 
                                    JOIN users f ON c.farmer_id = f.id
                                    LEFT JOIN users d ON o.delivery_partner_id = d.id
                                    WHERE o.id = '$order_id' AND o.buyer_id = '$buyer_id' LIMIT 1");

if (!$order_query || mysqli_num_rows($order_query) == 0) {
    header("Location: my_orders.php");
    exit();
}

$order = mysqli_fetch_assoc($order_query);
$total_cost = ($order['quantity'] * $order['price']) + $order['transport_cost'];

// Determine current tracking progress index
$tracking_status = strtolower($order['tracking_status']);
$status = strtolower($order['status']);

$step = 1; // Default: Order placed / Harvested
if ($order['original_parcel_image']) $step = 2; // Packed
if ($order['delivery_proof_image']) $step = 3; // Transit
if ($status === 'delivered') $step = 4; // Delivered
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quality Passport Trace | AgroNava</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --emerald-glow: rgba(16, 185, 129, 0.2);
            --dark-bg: #090e14;
        }

        body {
            background-color: var(--dark-bg);
            color: #f1f5f9;
            font-family: 'Inter', sans-serif;
        }

        .passport-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Quality Timeline Visual Style */
        .timeline {
            position: relative;
            padding-left: 40px;
            margin: 30px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 15px;
            width: 3px;
            background: rgba(255, 255, 255, 0.1);
        }

        .timeline-step {
            position: relative;
            margin-bottom: 40px;
        }

        .timeline-step::before {
            content: '';
            position: absolute;
            left: -35px;
            top: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #1e293b;
            border: 3px solid rgba(255, 255, 255, 0.1);
            z-index: 2;
            transition: all 0.3s ease;
        }

        .timeline-step.active::before {
            background: #10b981;
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 0 15px var(--emerald-glow);
        }

        .timeline-step.completed::before {
            background: #10b981;
            border-color: #10b981;
        }

        .timeline-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-md);
            padding: 24px;
            transition: all 0.3s ease;
        }

        .timeline-card:hover {
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 0 20px var(--emerald-glow);
        }

        .trace-image {
            width: 100%;
            max-width: 320px;
            border-radius: 8px;
            margin-top: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: block;
        }

        .quality-header-card {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(15, 118, 110, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: var(--radius-md);
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            box-shadow: 0 0 30px var(--emerald-glow);
        }

        .badge-organic {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    <header class="navbar" style="background: rgba(9, 14, 20, 0.85); border-color: rgba(255, 255, 255, 0.08);">
        <a href="dashboard.php" class="navbar-brand" style="color: #10b981;">
            <span>🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <a href="dashboard.php" style="color: var(--text-light); font-weight: 600;">Dashboard</a>
            <a href="marketplace.php" style="color: var(--text-light); font-weight: 600;">Marketplace</a>
            <a href="my_orders.php" style="color: var(--text-light); font-weight: 600;">My Orders</a>
        </div>
    </header>

    <div class="passport-container">

        <div class="quality-header-card">
            <div>
                <h4 style="margin: 0; color: #34d399; font-size: 15px; text-transform: uppercase; letter-spacing: 1px;">🛡️ Safe Custody Trace Active</h4>
                <h2 style="margin: 6px 0 0 0; font-size: 26px; font-family: 'Outfit', sans-serif; color: white;">Order Quality Passport</h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #94a3b8;">Cryptographic QR trace sequence verifying dispatch and logistics seal custody.</p>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 12px; color: #94a3b8; display: block; margin-bottom: 4px;">Escrow Value Secured:</span>
                <span style="font-size: 24px; font-weight: 900; color: #34d399; font-family: 'Outfit', sans-serif;">₹<?php echo number_format($total_cost); ?></span>
            </div>
        </div>

        <div class="glass-card" style="background: rgba(30, 41, 59, 0.2); border-color: rgba(255, 255, 255, 0.08); padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="color: #94a3b8; font-size: 13px;">Crop Cargo:</span>
                <h3 style="color: white; font-size: 18px; margin: 4px 0 0 0;"><?php echo htmlspecialchars($order['crop_name']); ?> (<?php echo number_format($order['quantity']); ?> kg)</h3>
            </div>
            <div>
                <?php if ($order['is_organic'] == 1): ?>
                    <span class="badge-organic">🌱 Certified Organic</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Custody Trace Timeline -->
        <div class="timeline">
            
            <!-- Step 1: Harvested & Listed -->
            <div class="timeline-step completed">
                <div class="timeline-card">
                    <h3 style="font-size: 16px; color: white; display: flex; align-items: center; gap: 8px;">
                        <span>🌱</span> Step 1: Harvested & Inspected
                    </h3>
                    <p style="color: #94a3b8; font-size: 13px; margin: 8px 0 12px 0; line-height: 1.5;">
                        Crop quality graded and verified at farmer node prior to packaging listings.
                    </p>
                    <div style="font-size: 13px; display: flex; gap: 24px; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 12px;">
                        <span>Grower: <b><?php echo htmlspecialchars($order['farmer_name']); ?></b></span>
                        <span>Harvest Date: <b><?php echo $order['harvest_date'] ? date("d M Y", strtotime($order['harvest_date'])) : 'Freshly Harvested'; ?></b></span>
                        <span>Grade Score: <b style="color: #34d399;"><?php echo htmlspecialchars($order['quality_grade'] ?? 'Grade-A'); ?></b></span>
                    </div>
                </div>
            </div>

            <!-- Step 2: Sealed & Dispatched -->
            <?php 
            $step_2_class = ($step >= 2) ? 'completed' : 'active';
            ?>
            <div class="timeline-step <?php echo $step_2_class; ?>">
                <div class="timeline-card">
                    <h3 style="font-size: 16px; color: white; display: flex; align-items: center; gap: 8px;">
                        <span>📦</span> Step 2: Sealed & Dispatched
                    </h3>
                    
                    <?php if ($order['original_parcel_image']) { ?>
                        <p style="color: #94a3b8; font-size: 13px; margin: 8px 0 12px 0; line-height: 1.5;">
                            Farmer has packed the harvest and registered packaging integrity with visual proof.
                        </p>
                        <div style="font-size: 13px; margin-bottom: 12px;">
                            Package Shield Type: <b><?php echo htmlspecialchars($order['package_type'] ?? 'Standard'); ?></b>
                        </div>
                        <img src="../uploads/parcels/<?php echo $order['original_parcel_image']; ?>" alt="Farmer Dispatch Proof" class="trace-image">
                    <?php } else { ?>
                        <p style="color: #64748b; font-size: 13px; margin: 8px 0 0 0; line-height: 1.5;">
                            ⏳ Awaiting farmer packaging verification proof upload.
                        </p>
                    <?php } ?>
                </div>
            </div>

            <!-- Step 3: Logistics Custody Verification -->
            <?php 
            $step_3_class = ($step >= 3) ? 'completed' : (($step == 2) ? 'active' : '');
            ?>
            <div class="timeline-step <?php echo $step_3_class; ?>">
                <div class="timeline-card">
                    <h3 style="font-size: 16px; color: white; display: flex; align-items: center; gap: 8px;">
                        <span>🚚</span> Step 3: Logistics Transit Custody
                    </h3>
                    
                    <?php if ($order['delivery_proof_image']) { ?>
                        <p style="color: #94a3b8; font-size: 13px; margin: 8px 0 12px 0; line-height: 1.5;">
                            Logistics partner has verified parcel seal integrity and accepted custody transit routes.
                        </p>
                        <div style="font-size: 13px; margin-bottom: 12px; display: flex; gap: 24px;">
                            <span>Agent: <b><?php echo htmlspecialchars($order['driver_name']); ?></b></span>
                            <span>Phone: <b><?php echo htmlspecialchars($order['driver_phone']); ?></b></span>
                        </div>
                        <img src="../uploads/seals/<?php echo $order['delivery_proof_image']; ?>" alt="Logistics Seal Proof" class="trace-image">
                    <?php } else { ?>
                        <p style="color: #64748b; font-size: 13px; margin: 8px 0 0 0; line-height: 1.5;">
                            ⏳ Logistics transit seal confirmation pending pickup.
                        </p>
                    <?php } ?>
                </div>
            </div>

            <!-- Step 4: Final Verification and Delivery -->
            <?php 
            $step_4_class = ($step >= 4) ? 'completed' : (($step == 3) ? 'active' : '');
            ?>
            <div class="timeline-step <?php echo $step_4_class; ?>">
                <div class="timeline-card">
                    <h3 style="font-size: 16px; color: white; display: flex; align-items: center; gap: 8px;">
                        <span>🤝</span> Step 4: Settle & Deliver
                    </h3>
                    
                    <?php if ($status === 'delivered') { ?>
                        <p style="color: #34d399; font-size: 13px; font-weight: 600; margin: 8px 0 0 0;">
                            ✅ Payout successfully released from Escrow Vault.
                        </p>
                        <p style="color: #94a3b8; font-size: 13px; margin: 8px 0 0 0; line-height: 1.5;">
                            Delivery completed and authorized securely with customer OTP code verification.
                        </p>
                    <?php } else { ?>
                        <p style="color: #64748b; font-size: 13px; margin: 8px 0 0 0; line-height: 1.5;">
                            ⏳ Awaiting final OTP delivery authorization handshake.
                        </p>
                        <div style="margin-top: 12px; background: rgba(52, 211, 153, 0.05); border: 1px dashed rgba(52, 211, 153, 0.2); padding: 12px; border-radius: 8px; font-size: 13px; color: #34d399;">
                            🔑 Your Secure Delivery Verification OTP: <b><?php echo htmlspecialchars($order['delivery_otp']); ?></b>
                        </div>
                    <?php } ?>
                </div>
            </div>

        </div>

    </div>

</body>
</html>
