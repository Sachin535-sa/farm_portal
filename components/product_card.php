<?php
// Reusable Product Card Component
// Expected variables:
// $card_data : associative array with crop details

$c_id = $card_data['id'];
$c_name = htmlspecialchars($card_data['crop_name']);
$c_price = (int)$card_data['price'];
$c_qty = (int)$card_data['quantity'];
$c_rating = isset($card_data['rating_avg']) ? number_format($card_data['rating_avg'], 1) : "0.0";
$c_reviews = isset($card_data['review_count']) ? (int)$card_data['review_count'] : 0;
$c_sales = isset($card_data['sales_volume']) ? (int)$card_data['sales_volume'] : 0;
$c_score = isset($card_data['distributor_score']) ? (int)$card_data['distributor_score'] : 0;
$c_badge = isset($card_data['distributor_badge']) ? htmlspecialchars($card_data['distributor_badge']) : "";
$c_farmer_name = isset($card_data['farmer_name']) ? htmlspecialchars($card_data['farmer_name']) : "Farmer";
$c_farmer_id = isset($card_data['farmer_id']) ? $card_data['farmer_id'] : 0;
$c_is_trending = isset($card_data['is_trending']) ? $card_data['is_trending'] : false;

$category = "Veg";
$emoji = "🥦";

if (stripos($c_name, "wheat") !== false || stripos($c_name, "pulse") !== false || stripos($c_name, "dal") !== false || stripos($c_name, "bean") !== false) {
    $category = "Wheat";
    $emoji = "<i class=\"ph-duotone ph-plant\"></i>";
} else if (stripos($c_name, "rice") !== false || stripos($c_name, "paddy") !== false) {
    $category = "Rice";
    $emoji = "🍚";
} else if (stripos($c_name, "potato") !== false) {
    $category = "Potato";
    $emoji = "🥔";
} else if (stripos($c_name, "tomato") !== false) {
    $category = "Tomato";
    $emoji = "🍅";
} else if (stripos($c_name, "mango") !== false || stripos($c_name, "apple") !== false || stripos($c_name, "orange") !== false || stripos($c_name, "fruit") !== false) {
    $category = "Fruit";
    $emoji = "🍎";
}

$expiry_badge = "";
if (!empty($card_data['expiry_date'])) {
    $diff_sec = strtotime($card_data['expiry_date']) - strtotime(date("Y-m-d"));
    $diff_days = (int)round($diff_sec / 86400);
    if ($diff_days <= 3) {
        $expiry_badge = '<span class="badge animate-pulse" style="background:var(--warning-light); color:#d97706; font-size:9.5px; font-weight:700; border:1px solid rgba(217,119,6,0.2); width:max-content; margin-top:4px;"><i class="ph-duotone ph-warning"></i> Expiring in '.$diff_days.'d</span>';
    } else {
        $expiry_badge = '<span class="badge" style="background:rgba(59, 130, 246, 0.08); color:#2563eb; font-size:9.5px; font-weight:600; width:max-content; margin-top:4px;"><i class="ph-duotone ph-calendar-blank"></i> Fresh ('.$diff_days.'d left)</span>';
    }
}

$bargained_price = isset($card_data['bargained_price']) ? (int)$card_data['bargained_price'] : null;
$display_price = $bargained_price ? $bargained_price : $c_price;
?>

<div class="glass-card product-card animate-fade <?php echo $c_is_trending ? 'trending-card' : 'animate-slide'; ?>" 
     data-category="<?php echo $category; ?>" 
     data-sales="<?php echo $c_sales; ?>" 
     data-rating="<?php echo $c_rating; ?>" 
     data-price="<?php echo $display_price; ?>" 
     data-index="<?php echo $c_id; ?>">
     
    <!-- Wishlist Toggle -->
    <button class="wishlist-btn" onclick="toggleWishlist(this, <?php echo $c_id; ?>)" title="Add to Wishlist">
        <i class="ph ph-heart"></i>
    </button>
    
    <?php if (!empty($card_data['crop_image']) && file_exists("../uploads/crops/" . $card_data['crop_image'])) { ?>
        <div class="crop-visual-badge" style="background-image: url('../uploads/crops/<?php echo htmlspecialchars($card_data['crop_image']); ?>');"></div>
    <?php } else { ?>
        <div class="crop-visual-badge">
            <?php echo $emoji; ?>
        </div>
    <?php } ?>
    
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <span class="badge" style="background: rgba(15, 118, 110, 0.08); color: var(--secondary); font-size: 10px; width: max-content;">
                <?php echo $category; ?>
            </span>
            <?php echo $expiry_badge; ?>
        </div>
        <?php if ($c_qty > 20) { ?>
            <span class="badge" style="background: rgba(16, 185, 129, 0.08); color: var(--primary-hover); font-size: 11px; font-weight: 700;">🟢 <?php echo $c_qty; ?> kg left</span>
        <?php } else { ?>
            <span class="badge animate-pulse" style="background: rgba(245, 158, 11, 0.08); color: #d97706; font-size: 11px; font-weight: 700; border: 1px solid rgba(245,158,11,0.15);"><i class="ph-duotone ph-warning"></i> Only <?php echo $c_qty; ?> kg left!</span>
        <?php } ?>
    </div>
    
    <!-- Sustainability & Delivery Badges -->
    <div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; margin-bottom: 8px;">
        <?php if (!empty($card_data['sustainability_badges'])) {
            $badges = explode(",", $card_data['sustainability_badges']);
            foreach ($badges as $badge) {
                $badge = trim($badge);
                if ($badge == 'organic') {
                    echo '<span class="badge" style="background: rgba(16, 185, 129, 0.05); color: var(--primary-hover); font-size: 9px; font-weight: 700;"><i class="ph-duotone ph-leaf"></i> Organic</span>';
                } else if ($badge == 'water_efficient') {
                    echo '<span class="badge" style="background: rgba(59, 130, 246, 0.05); color: #2563eb; font-size: 9px; font-weight: 700;"><i class="ph-duotone ph-drop"></i> Water Efficient</span>';
                } else if ($badge == 'eco_friendly') {
                    echo '<span class="badge" style="background: rgba(245, 158, 11, 0.05); color: #d97706; font-size: 9px; font-weight: 700;">♻ Eco-Friendly</span>';
                }
            }
        } ?>
        <!-- Delivery Time Tag -->
        <span class="badge" style="background: rgba(139, 92, 246, 0.08); color: #7c3aed; font-size: 9px; font-weight: 700;"><i class="ph-duotone ph-truck"></i> Est. 24-48 hrs</span>
    </div>

    <!-- Automated Price Auditor Warning Banners -->
    <?php if (isset($card_data['is_flagged']) && $card_data['is_flagged'] == 1) { ?>
        <div class="price-auditor-alert">
            <i class="ph-duotone ph-warning"></i> <strong>Price Auditor Warn:</strong> Suspicious supermarket rates variance under safety review.
        </div>
    <?php } ?>
    
    <h3 class="card-crop-name" style="font-size: 18px; margin-bottom: 8px; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 24px;">
        <?php echo $c_name; ?>
    </h3>
    
    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 12px; color: var(--text-muted); font-weight: 500;">
        <span style="color: #f59e0b; font-weight: 700;">⭐ <?php echo $c_rating; ?> <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">(<?php echo $c_reviews; ?>)</span></span>
        <span><i class="ph-duotone ph-trend-up"></i> <?php echo $c_sales; ?> kg sold</span>
    </div>
    
    <div class="farmer-info-box">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="color: var(--text-muted); font-weight: 500;">👨‍<i class="ph-duotone ph-plant"></i> Listed by:</span>
            <span style="font-size: 10.5px; color: var(--primary-hover); font-weight: 800; background: var(--success-light); padding: 1px 4px; border-radius: 3px;">
                QS: <?php echo $c_score; ?>%
            </span>
        </div>
        <div style="font-weight: 700; margin: 2px 0 6px 0;">
            <a href="../farmer/profile.php?id=<?php echo $c_farmer_id; ?>" style="color: var(--secondary); text-decoration: underline;">
                <?php echo $c_farmer_name; ?>
            </a>
        </div>
        <div style="font-size: 10.5px; color: var(--secondary); font-weight: 700; background: rgba(15, 118, 110, 0.05); padding: 4px 8px; border-radius: 4px; display: inline-block;">
            <i class="ph-duotone ph-shield-check"></i> <?php echo $c_badge; ?>
        </div>
    </div>

    <!-- Premium Price Comparison Matrix -->
    <div class="price-comparison-matrix">
        <span style="font-size: 10.5px; font-weight: 700; color: var(--secondary); display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="ph-duotone ph-chart-line-up"></i> Market Comparison Matrix:</span>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 11px;">
            <div style="display: flex; justify-content: space-between; padding-right: 4px; border-right: 1px solid var(--border);">
                <span style="color: var(--text-muted);">BigBasket:</span>
                <strong style="color: var(--danger);">₹<?php echo isset($card_data['ref_bigbasket_price']) && $card_data['ref_bigbasket_price'] ? $card_data['ref_bigbasket_price'] : (int)($c_price * 1.35); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding-left: 4px;">
                <span style="color: var(--text-muted);">Reliance:</span>
                <strong style="color: var(--danger);">₹<?php echo isset($card_data['ref_reliance_price']) && $card_data['ref_reliance_price'] ? $card_data['ref_reliance_price'] : (int)($c_price * 1.30); ?></strong>
            </div>
        </div>
        <div style="margin-top: 4px; font-size: 10px; display: flex; justify-content: space-between; color: var(--text-muted); border-top: 1px dashed rgba(0,0,0,0.05); padding-top: 4px;">
            <span><i class="ph-duotone ph-tractor"></i> Mandi wholesale: <strong>₹<?php echo isset($card_data['ref_mandi_price']) && $card_data['ref_mandi_price'] ? $card_data['ref_mandi_price'] : (int)($c_price * 0.92); ?></strong></span>
            <span style="color: var(--primary-hover); font-weight: 700;">Save ~30%!</span>
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: auto; padding-top: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; display: block; line-height: 1;">Price:</span>
                <?php if ($bargained_price) { ?>
                    <span style="font-size: 18px; font-weight: 800; color: var(--primary-hover);">
                        <span style="text-decoration: line-through; color: var(--text-muted); font-size: 14px; font-weight: 500; margin-right: 4px;">₹<?php echo $c_price; ?></span>₹<?php echo $bargained_price; ?><span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">/kg</span>
                    </span>
                    <span class="badge" style="background: var(--success-light); color: var(--primary-hover); font-size: 9px; padding: 2px 4px; border: 1px solid rgba(16,185,129,0.2); vertical-align: middle; display: inline-block;"><i class="ph-duotone ph-party-popper"></i> Deal Active</span>
                <?php } else { ?>
                    <span style="font-size: 18px; font-weight: 800; color: var(--primary-hover);">₹<?php echo $c_price; ?><span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">/kg</span></span>
                <?php } ?>
            </div>
            
            <button class="btn btn-primary trigger-order-modal" 
                    data-crop-id="<?php echo $c_id; ?>" 
                    data-crop-name="<?php echo htmlspecialchars($c_name); ?>" 
                    data-crop-price="<?php echo $display_price; ?>" 
                    data-crop-qty="<?php echo $c_qty; ?>"
                    style="padding: 8px 14px; font-size: 12px;">
                <?php echo $bargained_price ? 'Order Deal <i class="ph-duotone ph-handshake"></i>' : 'Order Now'; ?>
            </button>
        </div>
        
        <!-- Bargain Chat Link -->
        <a href="../chat.php?farmer_id=<?php echo $c_farmer_id; ?>&crop_id=<?php echo $c_id; ?>" 
           class="btn btn-secondary" style="padding: 8px; font-size: 12.5px; width: 100%; justify-content: center; margin-bottom: 4px;">
            💬 Negotiate / Chat
        </a>
    </div>
</div>
