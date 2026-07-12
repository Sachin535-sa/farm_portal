<?php
session_start();
include("../config/db.php");

// Session check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "buyer"){
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? mysqli_real_escape_string($conn, $_GET['order_id']) : '';

if (empty($order_id)) {
    header("Location: my_orders.php");
    exit();
}

// Fetch order and original parcel image if any
$sql = "SELECT o.*, c.crop_name, u.name as farmer_name 
        FROM orders o 
        JOIN crops c ON o.crop_id = c.id 
        JOIN users u ON c.farmer_id = u.id
        WHERE o.id = '$order_id' AND o.buyer_id = '$buyer_id' AND o.status = 'delivered'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: my_orders.php?err=order_not_found");
    exit();
}

$order = mysqli_fetch_assoc($result);

// Check if a complaint is already filed
$comp_check = mysqli_query($conn, "SELECT id FROM complaints WHERE order_id = '$order_id'");
if (mysqli_num_rows($comp_check) > 0) {
    header("Location: my_orders.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Damage | AgroNava</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    <style>
        .reporting-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .preview-box {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            background: rgba(0,0,0,0.01);
            margin-bottom: 20px;
            cursor: pointer;
            transition: var(--transition);
        }
        .preview-box:hover {
            border-color: var(--primary);
            background: rgba(16, 185, 129, 0.02);
        }
        .image-preview {
            max-width: 100%;
            max-height: 250px;
            border-radius: var(--radius-sm);
            display: none;
            margin: 10px auto 0 auto;
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
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
            <div class="user-badge">
                <span><i class='ph-duotone ph-shopping-cart'></i></span> <?php echo htmlspecialchars($_SESSION['name']); ?> (Buyer)
            </div>
            <a class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;" href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <div class="reporting-container">
        <div class="glass-card animate-slide" style="padding: 32px; background: rgba(255,255,255,0.95);">
            <div style="margin-bottom: 24px;">
                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; color: #ef4444; background: rgba(239, 68, 68, 0.08); padding: 4px 10px; border-radius: 50px; display: inline-block; margin-bottom: 10px;"><i class='ph-duotone ph-warning'></i> Automated Claim Verification</span>
                <h1 style="font-size: 28px; color: var(--dark); line-height: 1.2;">Report Damaged Delivery</h1>
                <p style="color: var(--text-muted); font-size: 13.5px; margin-top: 4px;">
                    Order #<?php echo $order['id']; ?> — <strong><?php echo htmlspecialchars($order['crop_name']); ?></strong> from <?php echo htmlspecialchars($order['farmer_name']); ?>
                </p>
            </div>

            <!-- Original reference parcel note if exists -->
            <?php if (!empty($order['original_parcel_image'])): ?>
                <div style="background: rgba(16, 185, 129, 0.04); border: 1px dashed rgba(16, 185, 129, 0.3); border-radius: var(--radius-md); padding: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                    <div style="width: 50px; height: 50px; border-radius: var(--radius-sm); background-image: url('../uploads/parcels/<?php echo htmlspecialchars($order['original_parcel_image']); ?>'); background-size: cover; background-position: center; border: 1px solid rgba(0,0,0,0.05); flex-shrink: 0;"></div>
                    <div>
                        <h4 style="color: var(--secondary); font-size: 12px; margin: 0; text-transform: uppercase;"><i class='ph-duotone ph-shield-check'></i> Reference Image Captured</h4>
                        <p style="font-size: 11.5px; color: var(--text-muted); margin: 2px 0 0 0;">Grower uploaded dispatch packaging reference. AI will perform side-by-side verification.</p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="process_complaint.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Upload Parcel Image</label>
                    <div class="preview-box" onclick="document.getElementById('parcel_image').click();">
                        <span id="preview-placeholder" style="font-size: 13px; color: var(--text-muted); font-weight: 500; display: block;">
                            📸 Click here or drag to upload package photo
                        </span>
                        <img id="image-viewer" class="image-preview" src="#" alt="Image Preview">
                    </div>
                    <input type="file" id="parcel_image" name="parcel_image" accept="image/*" required style="display: none;" onchange="previewImage(this);">
                </div>

                <div class="form-group">
                    <label class="form-label">Describe Issue / Reason</label>
                    <textarea name="reason" class="form-control" placeholder="Please describe exactly what was damaged (e.g. wet packaging, split sack, crushed container)" 
                              style="width: 100%; min-height: 100px; padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--border); font-family: inherit; font-size: 14px; outline: none; box-sizing: border-box; resize: vertical;" required></textarea>
                </div>

                <div style="display: flex; gap: 14px; margin-top: 24px;">
                    <a href="my_orders.php" class="btn btn-secondary" style="flex: 1; text-align: center; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.02);">Cancel Claim</a>
                    <button type="submit" class="btn btn-danger" style="flex: 2; justify-content: center; background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%); border: none; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);">
                        <i class='ph-duotone ph-warning-circle'></i> Submit AI Claim
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('image-viewer').src = e.target.result;
                document.getElementById('image-viewer').style.display = 'block';
                document.getElementById('preview-placeholder').style.display = 'none';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
    <!-- Scripting integration -->
    <script src="../assets/js/app.js"></script>
</body>
</html>
