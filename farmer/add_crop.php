<?php
session_start();
include("../config/db.php");

// Session validation
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "farmer"){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$msg = "";
$error_msg = "";

$is_edit = false;
$edit_id = "";
$crop_name = "";
$price = "";
$quantity = "";
$expiry_date = "";
$ref_reliance_price = "";
$ref_bigbasket_price = "";
$ref_mandi_price = "";
$sustainability_badges = "";
$is_auction = 0;
$starting_bid = "";
$auction_duration = 24;

// Check if in edit mode
if(isset($_GET['edit_id'])){
    $is_edit = true;
    $edit_id = mysqli_real_escape_string($conn, $_GET['edit_id']);
    
    // Fetch current details
    $res = mysqli_query($conn, "SELECT * FROM crops WHERE id='$edit_id' AND farmer_id='$farmer_id'");
    if(mysqli_num_rows($res) > 0) {
        $crop = mysqli_fetch_assoc($res);
        $crop_name = $crop['crop_name'];
        $price = $crop['price'];
        $quantity = $crop['quantity'];
        $expiry_date = $crop['expiry_date'];
        $ref_reliance_price = $crop['ref_reliance_price'];
        $ref_bigbasket_price = $crop['ref_bigbasket_price'];
        $ref_mandi_price = $crop['ref_mandi_price'];
        $sustainability_badges = $crop['sustainability_badges'];
        $is_auction = $crop['is_auction'];
        $starting_bid = $crop['starting_bid'];
        $auction_end_time = $crop['auction_end_time'];
        $auction_duration = $is_auction ? max(12, round((strtotime($auction_end_time) - time()) / 3600)) : 24;
    } else {
        header("Location: dashboard.php");
        exit();
    }
}

// Handling Add / Update action
if(isset($_POST['save_crop'])){
    $crop_name = mysqli_real_escape_string($conn, $_POST['crop_name']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    $ref_reliance_price = mysqli_real_escape_string($conn, $_POST['ref_reliance_price']);
    $ref_bigbasket_price = mysqli_real_escape_string($conn, $_POST['ref_bigbasket_price']);
    $ref_mandi_price = mysqli_real_escape_string($conn, $_POST['ref_mandi_price']);

    // Auction fields
    $is_auction = isset($_POST['is_auction']) ? 1 : 0;
    $starting_bid = $is_auction ? mysqli_real_escape_string($conn, $_POST['starting_bid']) : 0;
    $auction_duration = $is_auction ? mysqli_real_escape_string($conn, $_POST['auction_duration']) : 0;
    $auction_end_time = $is_auction ? date('Y-m-d H:i:s', strtotime("+$auction_duration hour")) : null;
    // Prepare SQL-friendly value for auction_end_time (NULL without quotes when not an auction)
    $auction_end_time_sql = $is_auction ? "'{$auction_end_time}'" : 'NULL';

    // Parse checked badges post array
    $badges_post = isset($_POST['badges']) ? $_POST['badges'] : [];
    $sustainability_badges = mysqli_real_escape_string($conn, implode(",", $badges_post));

    // Check expiry date is not past
    if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date("Y-m-d"))) {
        $error_msg = "<i class='ph-duotone ph-warning'></i> Invalid Expiry Date! The crop cannot be already expired.";
    }

    // Dynamic price fallback generation for direct trade showcase
    $num_price = (int)$price;
    if (empty($ref_reliance_price) || $ref_reliance_price == 0) {
        $ref_reliance_price = (int)($num_price * 1.30);
    }
    if (empty($ref_bigbasket_price) || $ref_bigbasket_price == 0) {
        $ref_bigbasket_price = (int)($num_price * 1.35);
    }
    if (empty($ref_mandi_price) || $ref_mandi_price == 0) {
        $ref_mandi_price = (int)($num_price * 0.92);
    }

    if (empty($expiry_date)) {
        $expiry_date = date("Y-m-d", strtotime("+7 days"));
    }

    // Automated Fraud Checker & Suspicious Pricing Audit Rule
    $is_flagged = 0;
    $flag_reason = "";
    if ($num_price > $ref_reliance_price * 2 || $num_price > $ref_bigbasket_price * 2) {
        $is_flagged = 1;
        $flag_reason = "Suspicious Pricing: Listed rate is $>200\%$ of retail supermarket rates.";
    } elseif ($num_price < $ref_mandi_price * 0.15) {
        $is_flagged = 1;
        $flag_reason = "Suspicious Pricing: Listed rate is dangerously dumping (under 15% local Mandi rate).";
    }

    // Handle Crop Image upload
    $crop_image = "";
    if (isset($_FILES['crop_image']) && $_FILES['crop_image']['error'] == 0) {
        $file_tmp = $_FILES['crop_image']['tmp_name'];
        $file_name = basename($_FILES['crop_image']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $crop_image = "crop_" . time() . "_" . uniqid() . "." . $ext;
            $target_path = "../uploads/crops/" . $crop_image;
            
            // Ensure folder exists
            if (!is_dir('../uploads/crops/')) {
                mkdir('../uploads/crops/', 0777, true);
            }
            
            move_uploaded_file($file_tmp, $target_path);
        } else {
            $error_msg = "<i class='ph-duotone ph-warning'></i> Invalid image format! Please upload JPG, PNG, or WEBP.";
        }
    }

    if (empty($error_msg)) {
        if($is_edit){
            $img_part = "";
            if (!empty($crop_image)) {
                $img_part = ", crop_image='$crop_image'";
            }
            $sql = "UPDATE crops SET crop_name='$crop_name', price='$price', quantity='$quantity', 
                    expiry_date='$expiry_date', ref_reliance_price='$ref_reliance_price', 
                    ref_bigbasket_price='$ref_bigbasket_price', ref_mandi_price='$ref_mandi_price',
                    sustainability_badges='$sustainability_badges', is_flagged='$is_flagged', flag_reason='$flag_reason', is_auction='$is_auction', starting_bid='$starting_bid', auction_end_time=$auction_end_time_sql
                    $img_part 
                    WHERE id='$edit_id' AND farmer_id='$farmer_id'";
            if(mysqli_query($conn, $sql)){
                header("Location: dashboard.php");
                exit();
            } else {
                $error_msg = "<i class='ph-duotone ph-warning'></i> Error updating crop: " . mysqli_error($conn);
            }
        } else {
            $sql = "INSERT INTO crops(farmer_id, crop_name, price, quantity, crop_image, expiry_date, ref_reliance_price, ref_bigbasket_price, ref_mandi_price, sustainability_badges, is_flagged, flag_reason, is_auction, starting_bid, auction_end_time)
                    VALUES('$farmer_id', '$crop_name', '$price', '$quantity', '$crop_image', '$expiry_date', '$ref_reliance_price', '$ref_bigbasket_price', '$ref_mandi_price', '$sustainability_badges', '$is_flagged', '$flag_reason', '$is_auction', '$starting_bid', $auction_end_time_sql)";
            if(mysqli_query($conn, $sql)){
                $msg = "<i class='ph-duotone ph-plant'></i> Crop listed successfully!";
                if ($is_flagged == 1) {
                    $msg .= " (<i class='ph-duotone ph-warning'></i> Note: This crop has been flagged by the automated safety system for suspicious pricing reviews.)";
                }
                // Reset input values after successful add
                $crop_name = "";
                $price = "";
                $quantity = "";
                $expiry_date = "";
                $ref_reliance_price = "";
                $ref_bigbasket_price = "";
                $ref_mandi_price = "";
                $sustainability_badges = "";
            } else {
                $error_msg = "<i class='ph-duotone ph-warning'></i> Error adding crop: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Crop Listing' : 'List New Crop'; ?> | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    
    <style>
        .auth-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-success {
            background: var(--success-light);
            color: var(--primary-hover);
            padding: 12px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid rgba(16, 185, 129, 0.15);
            text-align: left;
        }
        
        .form-error {
            background: var(--danger-light);
            color: var(--danger);
            padding: 12px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.15);
            text-align: left;
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="auth-bg">

    <div class="auth-card animate-slide" style="max-width: 500px; text-align: left;">
        
        <div style="text-align: center; margin-bottom: 24px;">
            <a href="dashboard.php" class="auth-logo">
                <span><i class="ph-duotone ph-plant"></i></span> AgroNava
            </a>
            <h2 style="font-size: 24px; color: var(--dark); margin-top: 8px;">
                <?php echo $is_edit ? '<i class="ph-duotone ph-gear"></i> Edit Crop Listing' : '➕ List Your Crop'; ?>
            </h2>
            <p style="color: var(--text-muted); font-size: 14px;">
                Publish your produce directly to buyers with direct price matrices
            </p>
        </div>

        <?php if($msg != "") { ?>
            <div class="form-success">
                <?php echo $msg; ?>
            </div>
        <?php } ?>

        <?php if($error_msg != "") { ?>
            <div class="form-error">
                <?php echo $error_msg; ?>
            </div>
        <?php } ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label class="form-label" for="crop_name">Crop Name</label>
                <input class="form-control" type="text" id="crop_name" name="crop_name" 
                       value="<?php echo htmlspecialchars($crop_name); ?>" placeholder="e.g. Basmati Rice (Organic)" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="price">Price (₹ per kg)</label>
                <input class="form-control" type="number" min="1" id="price" name="price" 
                       value="<?php echo htmlspecialchars($price); ?>" placeholder="e.g. 65" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="quantity">Available Quantity (in kg)</label>
                <input class="form-control" type="number" min="1" id="quantity" name="quantity" 
                       value="<?php echo htmlspecialchars($quantity); ?>" placeholder="e.g. 500" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="expiry_date">Product Expiry Date <i class='ph-duotone ph-calendar-blank'></i></label>
                <input class="form-control" type="date" id="expiry_date" name="expiry_date" 
                       value="<?php echo htmlspecialchars($expiry_date); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">When will this batch expire? Expired crops are hidden automatically.</span>
            </div>

            <!-- Sustainability Certifications Badges Selection -->
            <div class="form-group" style="background: rgba(16, 185, 129, 0.05); border: 1px dashed rgba(16, 185, 129, 0.2); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 24px;">
                <h4 style="font-size: 13.5px; color: var(--secondary); margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                    <i class='ph-duotone ph-leaf'></i> Sustainable Farming Badges
                </h4>
                <p style="font-size: 11.5px; color: var(--text-muted); margin: 0 0 14px 0; line-height: 1.4;">
                    Select eco-friendly practices used during cultivation. These render as trust tags on buyer catalogs!
                </p>
                
                <?php
                $badges_array = !empty($sustainability_badges) ? explode(",", $sustainability_badges) : [];
                ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--dark); cursor: pointer;">
                        <input type="checkbox" name="badges[]" value="organic" <?php echo in_array('organic', $badges_array) ? 'checked' : ''; ?>>
                        <span><i class='ph-duotone ph-leaf'></i> Organic Certified (No synthetic chemicals)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--dark); cursor: pointer;">
                        <input type="checkbox" name="badges[]" value="water_efficient" <?php echo in_array('water_efficient', $badges_array) ? 'checked' : ''; ?>>
                        <span><i class='ph-duotone ph-drop'></i> Water Efficient (Drip irrigation / rainwater)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--dark); cursor: pointer;">
                        <input type="checkbox" name="badges[]" value="eco_friendly" <?php echo in_array('eco_friendly', $badges_array) ? 'checked' : ''; ?>>
                        <span>♻ Eco-Friendly Methods (Biodiversity safe)</span>
                    </label>
                </div>
            </div>

            <div style="background: rgba(15, 118, 110, 0.04); border: 1px dashed rgba(15, 118, 110, 0.15); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 24px;">
                <h4 style="font-size: 13.5px; color: var(--secondary); margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                    <i class='ph-duotone ph-shopping-cart'></i> Supermarket Price Benchmarks (Optional)
                </h4>
                <p style="font-size: 11.5px; color: var(--text-muted); margin: 0 0 14px 0; line-height: 1.4;">
                    Set retail prices to show buyers their savings matrix. <em>Leave blank to auto-generate realistic benchmarks</em> (Reliance +30%, BigBasket +35%, Mandi -8%).
                </p>
                
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" for="ref_reliance_price" style="font-size: 12px;">Reliance Fresh Rate (₹/kg)</label>
                    <input class="form-control" type="number" min="0" id="ref_reliance_price" name="ref_reliance_price" 
                           value="<?php echo htmlspecialchars($ref_reliance_price); ?>" placeholder="Auto-calculated if blank">
                </div>
                
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" for="ref_bigbasket_price" style="font-size: 12px;">BigBasket Rate (₹/kg)</label>
                    <input class="form-control" type="number" min="0" id="ref_bigbasket_price" name="ref_bigbasket_price" 
                           value="<?php echo htmlspecialchars($ref_bigbasket_price); ?>" placeholder="Auto-calculated if blank">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="ref_mandi_price" style="font-size: 12px;">Local Mandi Wholesale Rate (₹/kg)</label>
                    <input class="form-control" type="number" min="0" id="ref_mandi_price" name="ref_mandi_price" 
                           value="<?php echo htmlspecialchars($ref_mandi_price); ?>" placeholder="Auto-calculated if blank">
                </div>
            </div>

            <!-- Auction Settings Section -->
            <div style="background: rgba(15, 118, 110, 0.04); border: 1px dashed rgba(15, 118, 110, 0.15); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 24px;">
                <h4 style="font-size: 13.5px; color: var(--secondary); margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                    <i class='ph-duotone ph-gavel'></i> Auction Settings (Optional)
                </h4>
                <p style="font-size: 11.5px; color: var(--text-muted); margin: 0 0 14px 0; line-height: 1.4;">
                    Enable bidding model for this listing. If disabled, buyers purchase instantly at the listing price.
                </p>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--dark); cursor: pointer; font-weight: 600;">
                        <input type="checkbox" id="is_auction" name="is_auction" value="1" <?php echo $is_auction ? 'checked' : ''; ?>>
                        <span>Enable Auction Bidding</span>
                    </label>
                </div>
                
                <div id="auction_details" class="auction-section <?php echo $is_auction ? '' : 'hidden'; ?>" style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed rgba(15, 118, 110, 0.15);">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label" for="starting_bid" style="font-size: 12px;">Starting Bid (₹ per kg)</label>
                        <input class="form-control" type="number" min="1" id="starting_bid" name="starting_bid" value="<?php echo htmlspecialchars($starting_bid ? $starting_bid : ''); ?>" placeholder="e.g., 50">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="auction_duration" style="font-size: 12px;">Auction Duration (hours)</label>
                        <select class="form-control" id="auction_duration" name="auction_duration">
                            <option value="12" <?php echo $auction_duration == 12 ? 'selected' : ''; ?>>12 hours</option>
                            <option value="24" <?php echo $auction_duration == 24 ? 'selected' : ''; ?>>24 hours</option>
                            <option value="48" <?php echo $auction_duration == 48 ? 'selected' : ''; ?>>48 hours</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 28px;">
                <label class="form-label" for="crop_image">Produce Photo Image (Optional)</label>
                <input class="form-control" type="file" id="crop_image" name="crop_image" accept="image/*">
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">Upload a photo of your harvest batch (JPG, PNG, or WEBP).</span>
            </div>
            
            <div style="display: flex; gap: 14px;">
                <a href="dashboard.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">
                    Cancel
                </a>
                <button type="submit" name="save_crop" class="btn btn-primary" style="flex: 1.5; justify-content: center;">
                    <?php echo $is_edit ? 'Save Changes' : 'Publish Listing <i class="ph-duotone ph-rocket"></i>'; ?>
                </button>
            </div>
            
        </form>
        
    </div>

    <script>
        document.getElementById('is_auction').addEventListener('change', function() {
            var details = document.getElementById('auction_details');
            var startingBid = document.getElementById('starting_bid');
            if (this.checked) {
                details.classList.remove('hidden');
                startingBid.setAttribute('required', 'required');
            } else {
                details.classList.add('hidden');
                startingBid.removeAttribute('required');
            }
        });
        
        // Initialize state on page load
        (function() {
            var isAuctionCheckbox = document.getElementById('is_auction');
            var startingBid = document.getElementById('starting_bid');
            if (isAuctionCheckbox.checked) {
                startingBid.setAttribute('required', 'required');
            } else {
                startingBid.removeAttribute('required');
            }
        })();
    </script>
</body>
</html>