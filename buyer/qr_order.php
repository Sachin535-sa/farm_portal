<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../config/db.php");

// ── Parameters from QR code ──────────────────────────────────────
$crop_id  = isset($_GET['crop_id'])  ? (int)$_GET['crop_id']  : 0;
$qty      = isset($_GET['qty'])      ? max(1, (int)$_GET['qty']) : 1;
$buyer_email = isset($_GET['email']) ? mysqli_real_escape_string($conn, $_GET['email']) : '';

$error   = '';
$order   = null;
$crop    = null;
$farmer  = null;
$order_id_placed = 0;

// ── Auto-login buyer if not already logged in ────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    if (!empty($buyer_email)) {
        $uq = mysqli_query($conn, "SELECT * FROM users WHERE email='$buyer_email' AND role='buyer' LIMIT 1");
        if ($uq && mysqli_num_rows($uq) > 0) {
            $u = mysqli_fetch_assoc($uq);
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['name']    = 'b. ' . $u['name'];
            $_SESSION['role']    = 'buyer';
        } else {
            $error = "Buyer account not found. Please login first.";
        }
    } else {
        // Redirect to login and come back
        header("Location: ../auth/login_buyer.php");
        exit();
    }
}

$buyer_id = $_SESSION['user_id'] ?? 0;

// ── Fetch crop details ───────────────────────────────────────────
if ($crop_id > 0 && empty($error)) {
    $cq = mysqli_query($conn, "SELECT c.*, u.name as farmer_name, u.email as farmer_email, u.mobile_no as farmer_mobile
                                FROM crops c JOIN users u ON c.farmer_id = u.id
                                WHERE c.id = '$crop_id' LIMIT 1");
    if ($cq && mysqli_num_rows($cq) > 0) {
        $crop = mysqli_fetch_assoc($cq);
    } else {
        $error = "Crop not found in database.";
    }
}

// ── Place order automatically (only once per page load via POST) ─
$order_placed = false;
if (!empty($crop) && empty($error) && isset($_POST['confirm_order'])) {
    $qty_to_order = (int)$_POST['qty'];
    if ($qty_to_order < 1) $qty_to_order = 1;
    $available = (int)$crop['quantity'];

    if ($available < $qty_to_order) {
        $error = "Insufficient stock. Only {$available}kg available.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // Deduct stock
            mysqli_query($conn, "UPDATE crops SET quantity = quantity - $qty_to_order WHERE id='$crop_id'");

            // Check for accepted bargain
            $bq = mysqli_query($conn, "SELECT proposed_price FROM bargains WHERE buyer_id='$buyer_id' AND crop_id='$crop_id' AND status='accepted' LIMIT 1");
            $final_price = (int)$crop['price'];
            if ($bq && mysqli_num_rows($bq) > 0) {
                $br = mysqli_fetch_assoc($bq);
                $final_price = (int)$br['proposed_price'];
            }

            // Insert order
            mysqli_query($conn, "INSERT INTO orders (buyer_id, crop_id, quantity, price, status)
                                  VALUES ('$buyer_id', '$crop_id', '$qty_to_order', '$final_price', 'pending')");
            $new_order_id = mysqli_insert_id($conn);

            // Notify buyer
            $bmsg = mysqli_real_escape_string($conn, "🛍️ QR Order Placed: {$qty_to_order}kg of {$crop['crop_name']} at ₹{$final_price}/kg. Order #{$new_order_id}");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('$buyer_id', '$bmsg')");

            // Notify farmer
            $fmsg = mysqli_real_escape_string($conn, "🌾 New QR Order #{$new_order_id}: Buyer ordered {$qty_to_order}kg of {$crop['crop_name']}. Please process it!");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ('{$crop['farmer_id']}', '$fmsg')");

            mysqli_commit($conn);
            $order_placed = true;
            $order_id_placed = $new_order_id;

            // Re-fetch crop for updated stock
            $cq2 = mysqli_query($conn, "SELECT * FROM crops WHERE id='$crop_id'");
            $crop = mysqli_fetch_assoc($cq2);

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Order failed. Please try again.";
        }
    }
}

// ── Fetch placed order for tracking ─────────────────────────────
if ($order_id_placed > 0) {
    $oq = mysqli_query($conn, "SELECT o.*, c.crop_name, c.price as listing_price, u.name as farmer_name
                                FROM orders o
                                JOIN crops c ON o.crop_id = c.id
                                JOIN users u ON c.farmer_id = u.id
                                WHERE o.id = '$order_id_placed' LIMIT 1");
    if ($oq && mysqli_num_rows($oq) > 0) {
        $order = mysqli_fetch_assoc($oq);
    }
}

// ── Delivery timeline helper ─────────────────────────────────────
$timeline_stages = [
    ['key' => 'pending',   'label' => 'Order Placed',    'icon' => '📝', 'eta' => 'Just now',          'desc' => 'Your order has been received by the farmer.'],
    ['key' => 'accepted',  'label' => 'Farmer Accepted', 'icon' => '🤝', 'eta' => 'Within 2 hours',    'desc' => 'Farmer has confirmed and is preparing your produce.'],
    ['key' => 'packed',    'label' => 'Packed & Ready',  'icon' => '📦', 'eta' => 'Within 12 hours',   'desc' => 'Your order is packed and labelled for dispatch.'],
    ['key' => 'shipped',   'label' => 'Out for Delivery', 'icon' => '🚚', 'eta' => '1–2 business days', 'desc' => 'Your parcel is on the way to your delivery address.'],
    ['key' => 'delivered', 'label' => 'Delivered!',       'icon' => '✅', 'eta' => '2–4 business days', 'desc' => 'Order successfully delivered. Enjoy your fresh produce!'],
];
$current_status = $order ? strtolower($order['status']) : 'pending';
$active_idx = 0;
foreach ($timeline_stages as $i => $s) {
    if ($s['key'] === $current_status) { $active_idx = $i; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Order & Tracking | AgroNava</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --green:  #16a34a;
    --green-light: #dcfce7;
    --cyan:   #0891b2;
    --amber:  #d97706;
    --slate:  #0f172a;
    --muted:  #64748b;
    --border: #e2e8f0;
    --bg:     #f8fafc;
    --card:   #ffffff;
    --radius: 16px;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    min-height: 100vh;
    color: var(--slate);
}

/* ── TOP NAV ─────────────────────────────────── */
.nav {
    background: white;
    border-bottom: 1px solid var(--border);
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
}
.nav-brand { font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 900; color: var(--slate); text-decoration: none; }
.nav-links a { color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 600; margin-left: 20px; }

/* ── PAGE WRAPPER ────────────────────────────── */
.page { max-width: 900px; margin: 0 auto; padding: 40px 20px 80px; }

/* ── ERROR STATE ─────────────────────────────── */
.error-card { background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius); padding: 32px; text-align: center; }
.error-card h2 { color: #dc2626; margin-bottom: 10px; }

/* ── SCAN CONFIRM CARD ───────────────────────── */
.scan-confirm {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    animation: slideUp 0.5s ease;
}

.scan-header {
    background: linear-gradient(135deg, #052e16, #166534);
    color: white;
    padding: 32px;
    text-align: center;
}
.scan-header .badge {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 50px;
    padding: 6px 18px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 16px;
}
.scan-header h1 { font-family: 'Outfit', sans-serif; font-size: 32px; font-weight: 900; margin-bottom: 8px; }
.scan-header p  { opacity: 0.8; font-size: 15px; }

.scan-body { padding: 32px; }

.crop-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.info-chip {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}
.info-chip .ic-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 6px; }
.info-chip .ic-value { font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 800; color: var(--slate); }
.info-chip .ic-unit  { font-size: 12px; color: var(--muted); }

.qty-selector { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
.qty-selector label { font-weight: 600; font-size: 14px; min-width: 120px; }
.qty-input {
    width: 120px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 18px;
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    text-align: center;
    color: var(--slate);
    transition: border-color 0.2s;
}
.qty-input:focus { outline: none; border-color: var(--green); }
.qty-hint { font-size: 12px; color: var(--muted); }

.order-total {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
}
.order-total .total-label { font-size: 14px; font-weight: 600; color: #15803d; }
.order-total .total-value { font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 900; color: #166534; }

.btn-place {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: white;
    border: none;
    border-radius: 12px;
    font-family: 'Outfit', sans-serif;
    font-size: 18px;
    font-weight: 800;
    cursor: pointer;
    letter-spacing: 0.5px;
    transition: all 0.3s;
    box-shadow: 0 4px 20px rgba(22,163,74,0.3);
}
.btn-place:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(22,163,74,0.4); }

/* ── ORDER PLACED SUCCESS ────────────────────── */
.success-banner {
    background: linear-gradient(135deg, #052e16, #166534);
    color: white;
    border-radius: var(--radius);
    padding: 32px;
    text-align: center;
    margin-bottom: 28px;
    animation: slideUp 0.4s ease;
}
.success-banner .checkmark {
    width: 72px; height: 72px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 36px;
    margin: 0 auto 16px;
    animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.success-banner h2 { font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 900; margin-bottom: 8px; }
.success-banner p  { opacity: 0.85; font-size: 15px; }
.order-number { display: inline-block; background: rgba(255,255,255,0.2); border-radius: 50px; padding: 8px 24px; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 18px; margin-top: 12px; letter-spacing: 1px; }

/* ── DELIVERY TIMELINE ───────────────────────── */
.tracking-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    margin-bottom: 24px;
}
.tracking-header {
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
    padding: 20px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.tracking-header h3 { font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 800; }
.live-badge {
    display: flex; align-items: center; gap: 8px;
    background: #dcfce7; color: #16a34a;
    border: 1px solid #bbf7d0;
    border-radius: 50px; padding: 6px 14px;
    font-size: 12px; font-weight: 700;
}
.live-dot { width: 8px; height: 8px; background: #16a34a; border-radius: 50%; animation: pulse-dot 1.5s infinite; }

.timeline { padding: 32px 28px; }

.timeline-item {
    display: flex;
    gap: 20px;
    position: relative;
    padding-bottom: 32px;
    animation: slideUp 0.5s ease both;
}
.timeline-item:last-child { padding-bottom: 0; }
.timeline-item:last-child .tl-line { display: none; }

.tl-left { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
.tl-icon {
    width: 52px; height: 52px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    border: 3px solid var(--border);
    background: white;
    position: relative;
    z-index: 1;
    transition: all 0.4s ease;
}
.tl-icon.completed { background: var(--green-light); border-color: var(--green); }
.tl-icon.active    { background: white; border-color: var(--cyan); box-shadow: 0 0 0 6px rgba(8,145,178,0.12); animation: ring-pulse 2s infinite; }
.tl-icon.upcoming  { opacity: 0.4; }

.tl-line {
    width: 2px;
    flex: 1;
    min-height: 20px;
    background: var(--border);
    margin-top: 4px;
}
.tl-line.done { background: var(--green); }

.tl-content { flex: 1; padding-top: 10px; }
.tl-stage-label { font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 800; color: var(--slate); margin-bottom: 4px; }
.tl-stage-label.upcoming { color: var(--muted); }
.tl-eta {
    display: inline-block;
    background: #f1f5f9;
    border-radius: 50px;
    padding: 3px 12px;
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: 6px;
}
.tl-eta.active-eta { background: rgba(8,145,178,0.1); color: var(--cyan); }
.tl-eta.done-eta   { background: var(--green-light); color: var(--green); }
.tl-desc { font-size: 13px; color: var(--muted); line-height: 1.5; }

/* ── ORDER SUMMARY BOTTOM ────────────────────── */
.summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}
.summary-box {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
}
.sb-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: var(--muted); margin-bottom: 6px; }
.sb-value { font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 800; color: var(--slate); }
.sb-sub   { font-size: 12px; color: var(--muted); margin-top: 2px; }

.action-row { display: flex; gap: 12px; }
.btn-secondary {
    flex: 1; padding: 14px;
    background: white; border: 1px solid var(--border);
    border-radius: 10px; text-align: center;
    text-decoration: none; font-weight: 700; font-size: 14px;
    color: var(--slate); transition: all 0.3s;
}
.btn-secondary:hover { border-color: var(--green); color: var(--green); }
.btn-primary-sm {
    flex: 1; padding: 14px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: white; border: none; border-radius: 10px;
    text-align: center; text-decoration: none;
    font-weight: 700; font-size: 14px; cursor: pointer;
    transition: all 0.3s;
}
.btn-primary-sm:hover { opacity: 0.9; }

/* ── ANIMATIONS ──────────────────────────────── */
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes popIn {
    from { transform: scale(0.5); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}
@keyframes pulse-dot {
    0%,100% { opacity: 1; transform: scale(1); }
    50%      { opacity: 0.5; transform: scale(1.3); }
}
@keyframes ring-pulse {
    0%,100% { box-shadow: 0 0 0 6px rgba(8,145,178,0.12); }
    50%      { box-shadow: 0 0 0 12px rgba(8,145,178,0.05); }
}

@media (max-width: 640px) {
    .crop-info-grid  { grid-template-columns: 1fr 1fr; }
    .summary-grid    { grid-template-columns: 1fr; }
    .action-row      { flex-direction: column; }
    .scan-header h1  { font-size: 24px; }
}
</style>
</head>
<body>

<nav class="nav">
    <a href="../index.php" class="nav-brand">🌾 AgroNava</a>
    <div class="nav-links">
        <a href="marketplace.php">Marketplace</a>
        <a href="my_orders.php">My Orders</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">

<?php if (!empty($error)): ?>
    <!-- ERROR STATE -->
    <div class="error-card">
        <div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
        <h2>Something went wrong</h2>
        <p style="color: #6b7280; margin: 10px 0 24px;"><?= htmlspecialchars($error) ?></p>
        <a href="marketplace.php" style="display:inline-block; background:#dc2626; color:white; padding:12px 28px; border-radius:10px; font-weight:700; text-decoration:none;">← Go to Marketplace</a>
    </div>

<?php elseif (!$order_placed && $crop): ?>
    <!-- STEP 1: CONFIRM ORDER -->
    <div class="scan-confirm">
        <div class="scan-header">
            <div class="badge">📱 QR Scan — Instant Order</div>
            <h1><?= htmlspecialchars($crop['crop_name']) ?></h1>
            <p>Listed by <strong><?= htmlspecialchars($crop['farmer_name']) ?></strong> · Review and confirm your order below</p>
        </div>
        <div class="scan-body">
            <div class="crop-info-grid">
                <div class="info-chip">
                    <div class="ic-label">Price</div>
                    <div class="ic-value">₹<?= $crop['price'] ?></div>
                    <div class="ic-unit">per kg</div>
                </div>
                <div class="info-chip">
                    <div class="ic-label">Stock Left</div>
                    <div class="ic-value"><?= $crop['quantity'] ?></div>
                    <div class="ic-unit">kg available</div>
                </div>
                <div class="info-chip">
                    <div class="ic-label">Rating</div>
                    <div class="ic-value">⭐ <?= number_format($crop['rating_avg'] ?? 4.5, 1) ?></div>
                    <div class="ic-unit"><?= $crop['review_count'] ?? 0 ?> reviews</div>
                </div>
            </div>

            <form method="POST" id="order-form">
                <input type="hidden" name="crop_id" value="<?= $crop_id ?>">

                <div class="qty-selector">
                    <label for="qty">Quantity (kg):</label>
                    <input type="number" class="qty-input" id="qty" name="qty"
                           value="<?= $qty ?>" min="1" max="<?= $crop['quantity'] ?>"
                           oninput="updateTotal()" required>
                    <span class="qty-hint">Max <?= $crop['quantity'] ?>kg available</span>
                </div>

                <div class="order-total">
                    <div>
                        <div class="total-label">Estimated Total</div>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">Based on listed price</div>
                    </div>
                    <div class="total-value" id="total-display">₹<?= $crop['price'] * $qty ?></div>
                </div>

                <button type="submit" name="confirm_order" class="btn-place" id="place-btn">
                    ✅ Confirm & Place Order Instantly
                </button>
            </form>

            <div style="text-align:center; margin-top: 16px;">
                <a href="marketplace.php" style="color: var(--muted); font-size: 13px; text-decoration: none;">← Back to Marketplace</a>
            </div>
        </div>
    </div>

<?php elseif ($order_placed && $order): ?>
    <!-- STEP 2: ORDER CONFIRMED + TRACKING -->

    <div class="success-banner">
        <div class="checkmark">✅</div>
        <h2>Order Placed Successfully!</h2>
        <p>Your QR scan order for <strong><?= htmlspecialchars($order['crop_name']) ?></strong> has been confirmed.</p>
        <div class="order-number">Order #<?= $order['id'] ?></div>
    </div>

    <!-- Live Delivery Tracking -->
    <div class="tracking-card">
        <div class="tracking-header">
            <h3>📦 Live Delivery Tracking</h3>
            <div class="live-badge">
                <div class="live-dot"></div>
                Live Status
            </div>
        </div>
        <div class="timeline">
            <?php foreach ($timeline_stages as $i => $stage):
                if ($i < $active_idx)       $state = 'completed';
                elseif ($i == $active_idx)  $state = 'active';
                else                        $state = 'upcoming';
            ?>
            <div class="timeline-item" style="animation-delay: <?= $i * 0.1 ?>s">
                <div class="tl-left">
                    <div class="tl-icon <?= $state ?>">
                        <?php if ($state === 'completed'): ?>✓
                        <?php else: ?><?= $stage['icon'] ?><?php endif; ?>
                    </div>
                    <div class="tl-line <?= $state === 'completed' ? 'done' : '' ?>"></div>
                </div>
                <div class="tl-content">
                    <div class="tl-stage-label <?= $state === 'upcoming' ? 'upcoming' : '' ?>">
                        <?= $stage['label'] ?>
                        <?php if ($state === 'active'): ?>
                            <span style="display:inline-block; background: rgba(8,145,178,0.1); color:#0891b2; font-size:11px; padding:2px 10px; border-radius:50px; margin-left:8px; font-weight:700;">CURRENT</span>
                        <?php elseif ($state === 'completed'): ?>
                            <span style="display:inline-block; background: #dcfce7; color:#16a34a; font-size:11px; padding:2px 10px; border-radius:50px; margin-left:8px; font-weight:700;">DONE</span>
                        <?php endif; ?>
                    </div>
                    <div class="tl-eta <?= $state === 'active' ? 'active-eta' : ($state === 'completed' ? 'done-eta' : '') ?>">
                        🕐 <?= $stage['eta'] ?>
                    </div>
                    <div class="tl-desc"><?= $stage['desc'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="summary-grid">
        <div class="summary-box">
            <div class="sb-label">Crop Ordered</div>
            <div class="sb-value"><?= htmlspecialchars($order['crop_name']) ?></div>
            <div class="sb-sub">Sold by <?= htmlspecialchars($order['farmer_name']) ?></div>
        </div>
        <div class="summary-box">
            <div class="sb-label">Quantity</div>
            <div class="sb-value"><?= $order['quantity'] ?> kg</div>
            <div class="sb-sub">At ₹<?= $order['price'] ?>/kg</div>
        </div>
        <div class="summary-box">
            <div class="sb-label">Total Amount</div>
            <div class="sb-value">₹<?= number_format($order['quantity'] * $order['price']) ?></div>
            <div class="sb-sub">Payment pending</div>
        </div>
        <div class="summary-box">
            <div class="sb-label">Estimated Delivery</div>
            <div class="sb-value" style="font-size:16px;"><?= date('d M Y', strtotime('+3 days')) ?></div>
            <div class="sb-sub">2–4 business days</div>
        </div>
    </div>

    <div class="action-row">
        <a href="my_orders.php" class="btn-secondary">📋 View All My Orders</a>
        <a href="marketplace.php" class="btn-primary-sm">🛒 Continue Shopping</a>
    </div>

<?php else: ?>
    <div class="error-card">
        <h2>⚠️ Invalid QR Code</h2>
        <p style="color:#6b7280; margin: 12px 0 24px;">This QR code does not contain valid crop or buyer information.</p>
        <a href="marketplace.php" style="display:inline-block; background:#16a34a; color:white; padding:12px 28px; border-radius:10px; font-weight:700; text-decoration:none;">Browse Marketplace</a>
    </div>
<?php endif; ?>

</div><!-- /page -->

<script>
const price = <?= $crop ? (int)$crop['price'] : 0 ?>;

function updateTotal() {
    const qty = parseInt(document.getElementById('qty')?.value) || 1;
    const total = document.getElementById('total-display');
    if (total) total.textContent = '₹' + (qty * price).toLocaleString('en-IN');
}

// Prevent double-submit
document.getElementById('order-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('place-btn');
    btn.textContent = '⏳ Placing Order...';
    btn.disabled = true;
});
</script>
</body>
</html>
