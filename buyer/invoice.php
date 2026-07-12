<?php
session_start();
include("../config/db.php");

// Session check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer"){
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

if(!isset($_GET['id'])){
    header("Location: my_orders.php");
    exit();
}

$order_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch Order Details with secure validation (must belong to this buyer)
$sql = "SELECT o.*, c.crop_name, c.price as listing_price, u_farmer.name as farmer_name, u_farmer.email as farmer_email, u_buyer.name as buyer_name, u_buyer.email as buyer_email 
        FROM orders o
        JOIN crops c ON o.crop_id = c.id
        JOIN users u_farmer ON c.farmer_id = u_farmer.id
        JOIN users u_buyer ON o.buyer_id = u_buyer.id
        WHERE o.id = '$order_id' AND o.buyer_id = '$buyer_id'";

$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) == 0){
    header("Location: my_orders.php");
    exit();
}

$order = mysqli_fetch_assoc($result);
$qty = (int)$order['quantity'];
$price = (int)$order['price'];
$subtotal = $qty * $price;
$delivery_charge = 150; // Mock delivery flat charge
$total = $subtotal + $delivery_charge;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Trade #<?php echo $order['id']; ?> | AgroNava</title>
    
    <!-- Link fonts only (avoid default style.css to keep print styles clean!) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            background: #f8fafc;
            padding: 40px 20px;
            margin: 0;
            line-height: 1.5;
        }
        
        .invoice-card {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
            padding: 48px;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 30px;
            margin-bottom: 30px;
        }
        
        .invoice-brand {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #0f766e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .invoice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .invoice-title {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        
        .billing-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 10px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .invoice-table th, .invoice-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .invoice-table th {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
        }
        
        .invoice-summary {
            margin-left: auto;
            width: 320px;
            border-top: 2px solid #e2e8f0;
            padding-top: 16px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .btn-print-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            transition: all 0.2s;
        }
        
        .btn-print-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .invoice-card {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }

        /* Logistics Timeline in Invoice */
        .timeline-wrapper {
            margin: 24px 0;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .timeline-title {
            font-size: 11px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .timeline-path {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding-bottom: 16px;
        }
        .timeline-path::before {
            content: "";
            position: absolute;
            top: 10px;
            left: 20px;
            right: 20px;
            height: 4px;
            background: #cbd5e1;
            z-index: 1;
        }
        .timeline-progress-bar {
            position: absolute;
            top: 10px;
            left: 20px;
            height: 4px;
            background: #0f766e;
            z-index: 2;
            transition: width 0.4s ease;
        }
        .timeline-node {
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }
        .timeline-node-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 3px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #64748b;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .timeline-node-label {
            margin-top: 6px;
            font-size: 9.5px;
            font-weight: 600;
            color: #64748b;
            text-align: center;
        }
        .timeline-node.active .timeline-node-circle {
            border-color: #10b981;
            background: #10b981;
            color: white;
        }
        .timeline-node.active .timeline-node-label {
            color: #10b981;
            font-weight: 700;
        }
        .timeline-node.completed .timeline-node-circle {
            border-color: #0f766e;
            background: #0f766e;
            color: white;
        }
        .timeline-node.completed .timeline-node-label {
            color: #0f766e;
        }
        .timeline-node.cancelled .timeline-node-circle {
            border-color: #ef4444;
            background: #ef4444;
            color: white;
        }
        .timeline-node.cancelled .timeline-node-label {
            color: #ef4444;
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <!-- Controls panel -->
    <div class="no-print" style="max-width: 800px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="my_orders.php" style="color: #64748b; text-decoration: none; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 6px;">
            ← Back to My Orders
        </a>
        <button class="btn-print-action" onclick="window.print();">
            🖨️ Print Trade Invoice
        </button>
    </div>

    <!-- Main Invoice Area -->
    <div class="invoice-card">
        
        <div class="invoice-header">
            <div>
                <div class="invoice-brand">
                    <span><i class="ph-duotone ph-plant"></i></span> AgroNava
                </div>
                <p style="color: #64748b; font-size: 12px; margin: 4px 0 0 0;">Zero Middlemen Agricultural Commerce</p>
            </div>
            <div style="text-align: right;">
                <h2 class="invoice-title">DIRECT TRADE INVOICE</h2>
                <p style="font-size: 14px; font-weight: 600; color: #0f766e; margin: 0;">Trade #<?php echo $order['id']; ?></p>
            </div>
        </div>

        <!-- Billing details grid -->
        <div class="invoice-grid">
            
            <div>
                <div class="billing-title">👨‍<i class="ph-duotone ph-plant"></i> Farmer (Supplier)</div>
                <h3 style="font-size: 16px; margin: 0 0 4px 0; color: #0f172a;"><?php echo htmlspecialchars($order['farmer_name']); ?></h3>
                <p style="font-size: 13.5px; color: #64748b; margin: 0 0 2px 0;">Agricultural Grower Portals</p>
                <p style="font-size: 13.5px; color: #64748b; margin: 0;">✉️ <?php echo htmlspecialchars($order['farmer_email']); ?></p>
            </div>
            
            <div>
                <div class="billing-title"><i class="ph-duotone ph-shopping-cart"></i> Buyer (Recipient)</div>
                <h3 style="font-size: 16px; margin: 0 0 4px 0; color: #0f172a;"><?php echo htmlspecialchars($order['buyer_name']); ?></h3>
                <p style="font-size: 13.5px; color: #64748b; margin: 0 0 2px 0;">Registered Portal Consumer</p>
                <p style="font-size: 13.5px; color: #64748b; margin: 0;">✉️ <?php echo htmlspecialchars($order['buyer_email']); ?></p>
            </div>
            
        </div>

        <div style="margin-bottom: 12px; font-size: 13.5px; color: #64748b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <span><i class="ph-duotone ph-calendar-blank"></i> <strong>Transaction Date:</strong> <?php echo date("F d, Y"); ?></span>
                <span style="margin-left: 30px;"><i class="ph-duotone ph-truck"></i> <strong>Status:</strong> <?php echo htmlspecialchars($order['status']); ?></span>
            </div>
            <div style="font-weight: 700; color: #10b981; font-size: 13px;">
                <i class="ph-duotone ph-rocket"></i> <strong>Fulfillment Est:</strong> 2 Days Domestic
            </div>
        </div>

        <!-- Logistics status timeline -->
        <?php 
        $status = strtolower($order['status']);
        $stages = [
            ['key' => 'pending', 'label' => 'Placed <i class="ph-duotone ph-file-text"></i>', 'num' => 0],
            ['key' => 'accepted', 'label' => 'Accepted <i class="ph-duotone ph-handshake"></i>', 'num' => 1],
            ['key' => 'packed', 'label' => 'Packed <i class="ph-duotone ph-package"></i>', 'num' => 2],
            ['key' => 'shipped', 'label' => 'Shipped <i class="ph-duotone ph-truck"></i>', 'num' => 3],
            ['key' => 'delivered', 'label' => 'Delivered <i class="ph-duotone ph-handshake"></i>', 'num' => 4]
        ];
        
        if ($status === 'cancelled') {
            echo '<div class="timeline-wrapper">';
            echo '<div class="timeline-title">Logistics Status Timeline</div>';
            echo '<div style="background: rgba(239, 68, 68, 0.08); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">';
            echo '<i class="ph-duotone ph-x-circle"></i> Order Cancelled & Items Returned to Stock';
            echo '</div>';
            echo '</div>';
        } else {
            $active_idx = 0;
            foreach ($stages as $idx => $stage) {
                if ($stage['key'] === $status) {
                    $active_idx = $idx;
                    break;
                }
            }
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

        <!-- Invoice Table -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th style="text-align: right;">Unit Price (₹/kg)</th>
                    <th style="text-align: right;">Quantity ordered (kg)</th>
                    <th style="text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight: 600; color: #0f172a;"><i class="ph-duotone ph-plant"></i> Fresh Harvest - <?php echo htmlspecialchars($order['crop_name']); ?></td>
                    <td style="text-align: right; color: #475569;">₹<?php echo $price; ?></td>
                    <td style="text-align: right; color: #475569;"><?php echo $qty; ?> kg</td>
                    <td style="text-align: right; font-weight: 700; color: #0f172a;">₹<?php echo number_format($subtotal); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Calculations -->
        <div class="invoice-summary">
            <div class="summary-row">
                <span style="color: #64748b;">Subtotal:</span>
                <span style="font-weight: 600;">₹<?php echo number_format($subtotal); ?></span>
            </div>
            <div class="summary-row">
                <span style="color: #64748b;">Flat Logistics Delivery:</span>
                <span style="font-weight: 600;">₹<?php echo number_format($delivery_charge); ?></span>
            </div>
            <div class="summary-row" style="font-size: 18px; border-top: 1px solid #f1f5f9; padding-top: 10px; margin-top: 6px;">
                <strong style="color: #0f172a;">Total Payable:</strong>
                <strong style="color: #0f766e;">₹<?php echo number_format($total); ?></strong>
            </div>
        </div>

        <!-- Dynamic UPI Settlement Status Block -->
        <?php if ($order['is_paid'] == 1): ?>
            <div style="margin-top: 30px; border: 1px solid rgba(16, 185, 129, 0.2); padding: 20px; border-radius: 8px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.02) 0%, rgba(52, 211, 153, 0.02) 100%); display: flex; align-items: center; justify-content: space-between; gap: 20px;">
                <div>
                    <strong style="color: #10b981; display: block; font-size: 14px; text-transform: uppercase; font-family: 'Outfit', sans-serif;"><i class="ph-duotone ph-credit-card"></i> PAYMENT VERIFIED & SETTLED</strong>
                    <span style="font-size: 12px; color: #64748b; display: block; margin-top: 4px;">
                        Your payment has been successfully recorded. Transaction Reference ID: <strong><?php echo htmlspecialchars($order['payment_txn']); ?></strong>
                    </span>
                </div>
                <div style="border: 2px dashed #10b981; color: #10b981; font-weight: 800; font-size: 14px; padding: 8px 16px; border-radius: 6px; transform: rotate(-3deg); text-transform: uppercase; font-family: 'Outfit', sans-serif; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.1);">
                    PAID & SECURED
                </div>
            </div>
        <?php elseif ($status === 'cancelled'): ?>
            <div style="margin-top: 30px; border: 1px solid rgba(239, 68, 68, 0.2); padding: 20px; border-radius: 8px; background: rgba(239, 68, 68, 0.02); text-align: center;">
                <strong style="color: #ef4444; display: block; font-size: 14px; text-transform: uppercase; font-family: 'Outfit', sans-serif;"><i class="ph-duotone ph-x-circle"></i> ORDER & PAYMENT CANCELLED</strong>
                <span style="font-size: 12px; color: #64748b; display: block; margin-top: 4px;">This transaction was cancelled and no payment settlement is required.</span>
            </div>
        <?php else: ?>
            <div style="margin-top: 30px; border: 1px solid rgba(99, 102, 241, 0.2); padding: 20px; border-radius: 8px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.02) 0%, rgba(168, 85, 247, 0.02) 100%); display: flex; align-items: center; gap: 20px;">
                <?php
                $upi_uri = "upi://pay?pa=agronava@okaxis&pn=AgroNavaDirect&am=" . $total . "&cu=INR&tn=Order_" . $order['id'];
                $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($upi_uri);
                ?>
                <div style="border: 1px solid #e2e8f0; padding: 6px; border-radius: 4px; background: white;">
                    <img src="<?php echo $qr_api; ?>" alt="Payment QR" style="width: 80px; height: 80px; display: block;">
                </div>
                <div>
                    <strong style="color: #4f46e5; display: block; font-size: 14px; text-transform: uppercase; font-family: 'Outfit', sans-serif;"><i class="ph-duotone ph-hourglass-high"></i> PAYMENT STATUS: PENDING SETTLEMENT</strong>
                    <span style="font-size: 12px; color: #64748b; display: block; margin-top: 4px;">
                        Please settle the total amount of <strong>₹<?php echo number_format($total); ?></strong> via UPI. Scan this dynamic code or settle in your <a href="my_orders.php" style="color: #4f46e5; font-weight: 600; text-decoration: underline;">Orders Dashboard</a> to verify.
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Handover Verification QR Box -->
        <?php if ($status !== 'delivered' && $status !== 'cancelled'): ?>
            <div class="no-print" style="margin-top: 30px; border: 1px dashed #e2e8f0; padding: 20px; border-radius: 8px; background: #fafafa; display: flex; align-items: center; gap: 20px;">
                <?php
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $base_url = $protocol . $host;
                if (strpos($_SERVER['REQUEST_URI'], '/farm_portal/') !== false) {
                    $base_url .= '/farm_portal/';
                } else {
                    $base_url .= '/';
                }
                $verify_url = $base_url . "farmer/verify_delivery.php?order_id=" . $order['id'];
                $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verify_url);
                ?>
                <a href="<?php echo $verify_url; ?>" style="display: block; border: 1px solid #e2e8f0; padding: 6px; border-radius: 4px; background: white;" title="Click to Emulate Mobile Scan <i class="ph-duotone ph-device-mobile"></i>">
                    <img src="<?php echo $qr_api; ?>" alt="Verification QR" style="width: 80px; height: 80px; display: block;">
                </a>
                <div>
                    <strong style="color: #0f766e; display: block; font-size: 13.5px; text-transform: uppercase;"><i class="ph-duotone ph-handshake"></i> Delivery Handover verification QR Badge</strong>
                    <span style="font-size: 12px; color: #64748b; display: block; margin-top: 4px;">Have the farmer scan this barcode during logistics physical handover to settle this trade safely in database. (Click image to simulate scan)</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="margin-top: 60px; border-top: 1px solid #f1f5f9; padding-top: 24px; text-align: center; font-size: 12px; color: #94a3b8;">
            <p>This invoice is electronically generated through the AgroNava platform.</p>
            <p style="margin-top: 4px; font-weight: 500; color: #64748b;"><i class="ph-duotone ph-handshake"></i> Directly supporting local farmers and sustainable commerce.</p>
        </div>

    </div>

</body>
</html>
