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
    header("Location: marketplace.php");
    exit();
}

$order_id = mysqli_real_escape_string($conn, $_GET['order_id']);

// Fetch order details for payment validation
$order_query = mysqli_query($conn, "SELECT o.*, c.crop_name, c.price as crop_price, u.name as farmer_name 
                                    FROM orders o 
                                    JOIN crops c ON o.crop_id = c.id 
                                    JOIN users u ON c.farmer_id = u.id
                                    WHERE o.id = '$order_id' AND o.buyer_id = '$buyer_id' LIMIT 1");

if (!$order_query || mysqli_num_rows($order_query) == 0) {
    header("Location: marketplace.php");
    exit();
}

$order = mysqli_fetch_assoc($order_query);

// Check if order is already paid
if ($order['is_paid'] == 1 || $order['status'] != 'pending_payment') {
    header("Location: my_orders.php");
    exit();
}

$total_crop_cost = $order['quantity'] * $order['price'];
$delivery_cost = $order['transport_cost'];
$total_amount = $total_crop_cost + $delivery_cost;

// Handle payment simulation
if (isset($_POST['confirm_payment'])) {
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $txn_id = "TXN" . strtoupper(bin2hex(random_bytes(6)));

    // Begin transaction for database integrity
    mysqli_begin_transaction($conn);
    try {
        // 1. Update order status and set paid
        mysqli_query($conn, "UPDATE orders SET is_paid = 1, payment_txn = '$txn_id', status = 'pending' WHERE id = '$order_id'");

        // 2. Record payment details
        mysqli_query($conn, "INSERT INTO payments (order_id, transaction_id, payment_method, amount, status) 
                             VALUES ('$order_id', '$txn_id', '$payment_method', '$total_amount', 'success')");

        // 3. Insert notification for buyer
        $notif_msg = mysqli_real_escape_string($conn, "💳 Payment Verified: Payout of ₹$total_amount secured in digital escrow for Order #$order_id.");
        mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$notif_msg')");

        // 4. Insert notification for farmer
        $farmer_id = $order['farmer_id'];
        $farmer_msg = mysqli_real_escape_string($conn, "💰 Payment Escrowed: Buyer has paid ₹$total_amount for your crop order #$order_id. Funds will release upon physical delivery verification.");
        mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$farmer_id', '$farmer_msg')");

        mysqli_commit($conn);
        header("Location: my_orders.php?success=paid");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Payment processing failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure UPI Escrow Terminal | AgroNava</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cyan-glow: rgba(6, 182, 212, 0.2);
            --cyber-dark: #090e14;
        }

        body {
            background-color: var(--cyber-dark);
            color: #f1f5f9;
            font-family: 'Inter', sans-serif;
        }

        .payment-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .gateway-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 32px;
            margin-top: 24px;
        }

        .escrow-shield {
            background: linear-gradient(135deg, rgba(8, 145, 178, 0.1), rgba(15, 118, 110, 0.05));
            border: 1px solid rgba(8, 145, 178, 0.25);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            box-shadow: 0 0 20px var(--cyan-glow);
        }

        .shield-icon {
            font-size: 32px;
        }

        .qr-card {
            background: white;
            color: #0f172a;
            border-radius: var(--radius-md);
            padding: 28px;
            text-align: center;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-frame {
            border: 2px solid #e2e8f0;
            padding: 12px;
            border-radius: 8px;
            background: #f8fafc;
            margin-bottom: 16px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 20px 0;
        }

        .method-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .method-btn:hover, .method-btn.active {
            background: var(--secondary);
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(8, 145, 178, 0.25);
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .amount-row.total {
            border-top: 2px dashed rgba(255, 255, 255, 0.1);
            border-bottom: none;
            font-size: 20px;
            font-weight: 800;
            color: #06b6d4;
            padding-top: 18px;
        }
    </style>
</head>
<body>

    <header class="navbar" style="background: rgba(9, 14, 20, 0.85); border-color: rgba(255, 255, 255, 0.08);">
        <a href="dashboard.php" class="navbar-brand" style="color: #06b6d4;">
            <span>🌾</span> AgroNava
        </a>
        <div class="navbar-menu">
            <a href="dashboard.php" style="color: var(--text-light); font-weight: 600;">Dashboard</a>
            <a href="marketplace.php" style="color: var(--text-light); font-weight: 600;">Marketplace</a>
            <a href="my_orders.php" style="color: var(--text-light); font-weight: 600;">My Orders</a>
        </div>
    </header>

    <div class="payment-container">
        
        <div class="escrow-shield">
            <div class="shield-icon">🛡️</div>
            <div>
                <h4 style="margin: 0; color: #06b6d4; font-size: 16px;">AgroNava Digital Escrow Guarantee</h4>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #94a3b8; line-height: 1.4;">
                    Your payment remains locked securely in the platform vault. Funds are only released to the farmer after the crop package is physically delivered and verified via OTP.
                </p>
            </div>
        </div>

        <h1 style="font-size: 32px; font-family: 'Outfit', sans-serif;">Gateway Terminal</h1>
        <p style="color: #94a3b8; font-size: 14px;">Authorize secure settlement transaction for crop delivery order.</p>

        <div class="gateway-grid">
            
            <!-- Left Panel: Summary and Simulation -->
            <div class="glass-card" style="background: rgba(30, 41, 59, 0.4); border-color: rgba(255, 255, 255, 0.08);">
                <h3 style="font-size: 20px; margin-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 12px; color: white;">Order Details</h3>
                
                <div class="amount-row">
                    <span style="color: #94a3b8;">Order ID</span>
                    <span style="font-weight: 600;">#<?php echo $order_id; ?></span>
                </div>
                <div class="amount-row">
                    <span style="color: #94a3b8;">Crop Product</span>
                    <span style="font-weight: 600;"><?php echo htmlspecialchars($order['crop_name']); ?></span>
                </div>
                <div class="amount-row">
                    <span style="color: #94a3b8;">Grower</span>
                    <span style="font-weight: 600;"><?php echo htmlspecialchars($order['farmer_name']); ?></span>
                </div>
                <div class="amount-row">
                    <span style="color: #94a3b8;">Procured Weight</span>
                    <span style="font-weight: 600;"><?php echo number_format($order['quantity']); ?> kg</span>
                </div>
                <div class="amount-row">
                    <span style="color: #94a3b8;">Base Crop Value</span>
                    <span style="font-weight: 600;">₹<?php echo number_format($total_crop_cost); ?></span>
                </div>
                <div class="amount-row">
                    <span style="color: #94a3b8;">Smart Logistics Fee</span>
                    <span style="font-weight: 600;">₹<?php echo number_format($delivery_cost); ?></span>
                </div>
                <div class="amount-row total">
                    <span>Total Settlement Amount</span>
                    <span>₹<?php echo number_format($total_amount); ?></span>
                </div>

                <form method="POST" id="payment-form" style="margin-top: 30px;">
                    <input type="hidden" name="payment_method" id="selected-method" value="GPay">
                    
                    <h4 style="font-size: 14px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">Select Payment Service</h4>
                    <div class="payment-methods">
                        <div class="method-btn active" onclick="selectMethod('GPay')">Google Pay</div>
                        <div class="method-btn" onclick="selectMethod('PhonePe')">PhonePe</div>
                        <div class="method-btn" onclick="selectMethod('Paytm')">Paytm</div>
                        <div class="method-btn" onclick="selectMethod('BHIM')">BHIM UPI</div>
                    </div>

                    <button type="submit" name="confirm_payment" class="btn btn-primary" style="width: 100%; padding: 16px; font-size: 16px; font-weight: 700; background: linear-gradient(135deg, #06b6d4, #0891b2); border-color: #06b6d4; box-shadow: 0 4px 20px rgba(6, 182, 212, 0.3);">
                        ⚡ Simulate Payment Success
                    </button>
                </form>
            </div>

            <!-- Right Panel: UPI QR Code -->
            <div class="qr-card">
                <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 8px; color: #0f172a;">Scan to Pay via UPI</h3>
                <p style="color: #64748b; font-size: 12px; margin-bottom: 20px;">Use any UPI compatible app to verify and secure escrow funds.</p>
                
                <div class="qr-frame">
                    <?php
                    // Build upi string
                    $upi_payload = "upi://pay?pa=agronava@upi&pn=AgroNava&am=" . $total_amount . "&cu=INR&tn=Order" . $order_id;
                    $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($upi_payload);
                    ?>
                    <img src="<?php echo $qr_api_url; ?>" alt="UPI QR Code" style="display: block; width: 220px; height: 220px;">
                </div>
                
                <div style="font-family: 'Outfit', sans-serif; font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 8px;">
                    ₹<?php echo number_format($total_amount); ?>
                </div>
                <div style="color: #94a3b8; font-size: 12px; margin-top: 4px;">merchant_id: agronava@upi</div>
            </div>

        </div>

    </div>

    <script>
        function selectMethod(method) {
            document.getElementById('selected-method').value = method;
            
            // Toggle active classes
            const buttons = document.querySelectorAll('.method-btn');
            buttons.forEach(btn => {
                if (btn.innerText.includes(method)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }
    </script>

</body>
</html>
