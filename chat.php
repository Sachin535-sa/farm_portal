<?php
session_start();
include("config/db.php");

// Session check
if(!isset($_SESSION['user_id'])){
    header("Location: auth/login.php");
    exit();
}

$role = $_SESSION['role'];
$user_name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];

// Fetch notifications
$user_id_clean = mysqli_real_escape_string($conn, $user_id);
$notif_query = "SELECT * FROM notifications WHERE user_id = '$user_id_clean' ORDER BY created_at DESC LIMIT 5";
$notif_res = mysqli_query($conn, $notif_query);
$unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$user_id_clean' AND is_read = 0";
$unread_count_res = mysqli_query($conn, $unread_count_query);
$unread_count = 0;
if ($unread_count_res) {
    $unread_count_row = mysqli_fetch_assoc($unread_count_res);
    $unread_count = (int)$unread_count_row['count'];
}

// Get farmer/buyer and crop details from parameters
$crop_id = isset($_GET['crop_id']) ? mysqli_real_escape_string($conn, $_GET['crop_id']) : '';

// Resolve buyer and farmer IDs depending on role
$buyer_id = ($role == 'buyer') ? $user_id : (isset($_GET['buyer_id']) ? mysqli_real_escape_string($conn, $_GET['buyer_id']) : '');
$farmer_id = ($role == 'farmer') ? $user_id : (isset($_GET['farmer_id']) ? mysqli_real_escape_string($conn, $_GET['farmer_id']) : '');

$farmer_name = "Farmer";
$buyer_name = "Buyer";
$crop_name = "Premium Produce";
$crop_price = "0";

// Fetch crop details
if($crop_id != '') {
    $res_crop = mysqli_query($conn, "SELECT c.*, u.name as farmer_name FROM crops c JOIN users u ON c.farmer_id = u.id WHERE c.id='$crop_id'");
    if(mysqli_num_rows($res_crop) > 0) {
        $crop = mysqli_fetch_assoc($res_crop);
        $crop_name = $crop['crop_name'];
        $crop_price = $crop['price'];
        $farmer_name = $crop['farmer_name'];
    }
}

// Fetch other party details
$other_user_id = ($role == 'buyer') ? $farmer_id : $buyer_id;
$other_name = "User";
if (!empty($other_user_id)) {
    $res = mysqli_query($conn, "SELECT name, role FROM users WHERE id='$other_user_id'");
    if (mysqli_num_rows($res) > 0) {
        $other = mysqli_fetch_assoc($res);
        $other_name = $other['name'];
        if ($role == 'farmer') {
            $buyer_name = $other_name;
        }
    }
}

// PHP Form Handlers for database operations
// 1. Save chat messages + AgroBot auto-reply
if (isset($_POST['send_message'])) {
    $msg_text = mysqli_real_escape_string($conn, $_POST['message']);
    if (!empty($msg_text) && !empty($crop_id)) {
        $receiver_id = ($role == 'buyer') ? $farmer_id : $buyer_id;
        $insert_query = "INSERT INTO chat_messages (sender_id, receiver_id, crop_id, message)
                         VALUES ('$user_id', '$receiver_id', '$crop_id', '$msg_text')";
        mysqli_query($conn, $insert_query);

        // ── AgroBot: Auto-reply when buyer sends a message ──────────────────
        if ($role == 'buyer' && !empty($farmer_id)) {
            $msg_lower = strtolower($_POST['message']);
            $bot_reply = '';

            // === PRICE / BARGAIN KEYWORDS ===
            if (preg_match('/\b(price|cost|rate|charge|expensive|cheap|₹|rs|rupee|amount|quote|offer|bargain|negotiate|deal|discount|reduce|low|high|afford)\b/', $msg_lower)) {
                $replies = [
                    "<i class='ph-duotone ph-handshake'></i> Namaste Ji! Thank you for your interest. Our listed price of ₹{$crop_price}/kg reflects the premium quality of our {$crop_name}. However, for bulk orders we are open to negotiation — please use the 'Propose Offer' panel above to submit your price!",
                    "<i class='ph-duotone ph-currency-circle-dollar'></i> We understand your concern about pricing. For orders above 100kg, we can discuss a special rate. Please submit your proposed price using the bargain tool and we will review it immediately!",
                    "<i class='ph-duotone ph-plant'></i> Our {$crop_name} is priced at ₹{$crop_price}/kg — this includes farm-fresh quality assurance. We are open to reasonable offers. Use the bargain panel to make a formal proposal!",
                    "<i class='ph-duotone ph-check-circle'></i> We appreciate your interest! For a fair deal, please submit your target price using the Propose Offer tool. We review all offers within the same day!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === ACCEPT / SATISFIED / AGREE KEYWORDS ===
            } elseif (preg_match('/\b(ok|okay|agree|accept|satisfied|deal|done|perfect|fine|sure|alright|yes|haan|theek|chalega|chalte)\b/', $msg_lower)) {
                $replies = [
                    "<i class='ph-duotone ph-party-popper'></i> Excellent! We are delighted to do business with you! Please proceed to place your order from the marketplace. We will ensure timely dispatch of your {$crop_name}!",
                    "<i class='ph-duotone ph-check-circle'></i> Great news! We are satisfied and ready to fulfil your order. Kindly place the order through the marketplace — we will prioritize your delivery!",
                    "<i class='ph-duotone ph-handshake'></i> Deal confirmed! It's a pleasure doing business with you on AgroNava. Please go ahead and place the order — we will ensure top quality packaging and fast dispatch!",
                    "🌟 Wonderful! Let's do business! Your {$crop_name} order will be prepared fresh after you place it from the marketplace. Thank you for choosing us!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === DELIVERY / SHIPPING KEYWORDS ===
            } elseif (preg_match('/\b(deliver|delivery|ship|shipping|transport|logistics|arrival|dispatch|send|receive|time|days|when|how long|location|address)\b/', $msg_lower)) {
                $replies = [
                    "<i class='ph-duotone ph-truck'></i> We typically dispatch within 24-48 hours of order confirmation. Delivery to most districts takes 2-4 business days. For your location, we can arrange direct farm-to-door service!",
                    "<i class='ph-duotone ph-package'></i> Our standard delivery timeline is 2-3 business days after payment confirmation. We partner with reliable logistics providers to ensure your {$crop_name} arrives fresh!",
                    "🗓️ We process all orders on the same day if placed before 2 PM. Dispatch happens the following morning with full tracking updates sent to your registered mobile!",
                    "🚛 Delivery is available across all major districts. For bulk orders above 200kg, we offer free farm pickup as well. Please share your delivery PIN code for an accurate ETA!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === QUALITY / ORGANIC / FRESH KEYWORDS ===
            } elseif (preg_match('/\b(quality|fresh|organic|natural|pesticide|chemical|grade|certified|pure|clean|test|safe|standard|guarantee|genuine)\b/', $msg_lower)) {
                $replies = [
                    "<i class='ph-duotone ph-leaf'></i> Our {$crop_name} is 100% naturally grown using traditional farming practices. We follow all APMC quality standards and our produce is regularly tested for pesticide residues.",
                    "<i class='ph-duotone ph-check-circle'></i> Quality is our top priority! All our crops are harvested at peak freshness and immediately sorted into Grade-A lots. We can provide quality certificates on request!",
                    "<i class='ph-duotone ph-plant'></i> Our farm follows sustainable and eco-friendly practices. The {$crop_name} you see listed is from the latest harvest — absolutely fresh, no cold storage involved!",
                    "🏆 We take pride in our produce quality. Each batch goes through manual sorting, cleaning, and packaging before dispatch. Customer satisfaction is our biggest reward!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === QUANTITY / BULK / STOCK KEYWORDS ===
            } elseif (preg_match('/\b(quantity|kg|kilo|ton|tonne|quintal|stock|available|bulk|order|how much|minimum|maximum|lot|load)\b/', $msg_lower)) {
                $replies = [
                    "<i class='ph-duotone ph-package'></i> We currently have good stock available of {$crop_name}. Minimum order is 10kg and we can fulfil bulk orders up to our listed quantity. What quantity are you looking for?",
                    "<i class='ph-duotone ph-plant'></i> Our current {$crop_name} stock is fresh and ready for dispatch. For bulk orders above 50kg, we offer priority processing and can negotiate on pricing!",
                    "<i class='ph-duotone ph-check-circle'></i> We can accommodate your quantity requirements. Please let us know your exact requirement and we will confirm availability and dispatch timeline immediately!",
                    "💼 For commercial or wholesale quantities, please mention your requirement and we will provide a customized quotation with volume-based pricing!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === PAYMENT / UPI / TERMS KEYWORDS ===
            } elseif (preg_match('/\b(payment|pay|upi|online|cash|advance|credit|cod|terms|invoice|receipt|bill|bank|transfer|neft|rtgs|gpay|phonepe|paytm)\b/', $msg_lower)) {
                $replies = [
                    "<i class='ph-duotone ph-credit-card'></i> We accept UPI, bank transfer (NEFT/RTGS), and online payment through the AgroNava platform. Full payment is required before dispatch for security.",
                    "🏦 Our preferred payment mode is UPI or bank transfer. Once your order is placed on the platform, payment instructions will be shared automatically.",
                    "<i class='ph-duotone ph-check-circle'></i> Payment is fully secured through AgroNava's platform. We generate a proper invoice after every successful transaction for your records.",
                    "<i class='ph-duotone ph-currency-circle-dollar'></i> We offer flexible payment terms for long-term buyers. For your first order, advance payment is required. Subsequent orders can have net-7 day credit terms!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === GREETING / HELLO / HI KEYWORDS ===
            } elseif (preg_match('/\b(hello|hi|hey|namaste|namaskar|good morning|good afternoon|good evening|how are you|greetings|kem cho|sat sri akal)\b/', $msg_lower)) {
                $replies = [
                    "🙏 Namaste Ji! Welcome to my listing on AgroNava. I am delighted to connect with you! I have fresh {$crop_name} available at ₹{$crop_price}/kg. How can I assist you today?",
                    "👋 Hello! Thank you for reaching out. I am a verified AgroNava farmer and I have quality {$crop_name} ready for sale. Please feel free to ask anything!",
                    "<i class='ph-duotone ph-plant'></i> Sat Sri Akal / Namaste! Great to hear from you. I am here to assist you with your {$crop_name} procurement. What would you like to know?"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === COMPLAINT / ISSUE / PROBLEM KEYWORDS ===
            } elseif (preg_match('/\b(problem|issue|complaint|bad|wrong|damaged|rotten|delay|late|refund|return|cancel|worst|terrible|poor)\b/', $msg_lower)) {
                $replies = [
                    "🙏 I sincerely apologize for the inconvenience. Please share the details of the issue and I will resolve it at the earliest. Customer satisfaction is my priority!",
                    "😔 I am sorry to hear about this. Please raise a formal complaint through AgroNava support and I will cooperate fully to resolve the matter as soon as possible.",
                    "<i class='ph-duotone ph-check-circle'></i> I take every feedback seriously. Please describe the problem and I will arrange either a replacement or a refund depending on the situation. Your trust matters!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === THANK YOU / APPRECIATE KEYWORDS ===
            } elseif (preg_match('/\b(thank|thanks|thankyou|appreciate|grateful|dhanyawad|shukriya|great|excellent|wonderful|amazing|good)\b/', $msg_lower)) {
                $replies = [
                    "🙏 Thank you so much for your kind words! It is always a pleasure to serve you on AgroNava. Looking forward to a long-term business relationship!",
                    "💚 We are grateful for your trust in our produce. Your satisfaction drives us to maintain the highest quality. Please visit again for fresh stock updates!",
                    "🌟 Thank you Ji! It is farmers like us and buyers like you who make AgroNava's direct trade ecosystem thrive. See you at the next harvest!"
                ];
                $bot_reply = $replies[array_rand($replies)];

            // === DEFAULT FALLBACK ===
            } else {
                $replies = [
                    "<i class='ph-duotone ph-plant'></i> Thank you for your message! I will get back to you shortly regarding your inquiry about {$crop_name}. For urgent queries, please use the Bargain Propose tool above!",
                    "🙏 Namaste Ji! I have noted your message. If you have any questions about price, quality, delivery, or payment — feel free to ask and I will respond promptly!",
                    "<i class='ph-duotone ph-check-circle'></i> Message received! I am reviewing your query about {$crop_name}. If you wish to negotiate the price, please use the Propose Offer panel for a formal discussion!",
                    "💬 Thank you for connecting on AgroNava! I am here to help with all your procurement needs. Please feel free to ask anything about this listing!"
                ];
                $bot_reply = $replies[array_rand($replies)];
            }

            // Insert AgroBot reply from farmer's perspective
            if (!empty($bot_reply)) {
                $bot_reply_escaped = mysqli_real_escape_string($conn, $bot_reply);
                mysqli_query($conn, "INSERT INTO chat_messages (sender_id, receiver_id, crop_id, message)
                                     VALUES ('$farmer_id', '$buyer_id', '$crop_id', '$bot_reply_escaped')");
            }
        }
        // ── End AgroBot ─────────────────────────────────────────────────────

        header("Location: chat.php?farmer_id=$farmer_id&buyer_id=$buyer_id&crop_id=$crop_id");
        exit();
    }
}

// 2. Propose bargain
if (isset($_POST['propose_bargain'])) {
    $proposed_price = (int)$_POST['proposed_price'];
    if ($proposed_price > 0 && !empty($crop_id)) {
        // Clear any existing bargain between this buyer and farmer for this crop
        mysqli_query($conn, "DELETE FROM bargains WHERE buyer_id='$buyer_id' AND crop_id='$crop_id'");
        
        // Insert new bargain
        $insert_bargain = "INSERT INTO bargains (buyer_id, farmer_id, crop_id, proposed_price, status) 
                           VALUES ('$buyer_id', '$farmer_id', '$crop_id', '$proposed_price', 'pending')";
        mysqli_query($conn, $insert_bargain);
        
        // Also insert an automatic chat message to notify the farmer
        $msg_text = "💡 Bargain Proposal: Offered ₹{$proposed_price}/kg for this crop (Listed Price: ₹{$crop_price}/kg).";
        mysqli_query($conn, "INSERT INTO chat_messages (sender_id, receiver_id, crop_id, message) 
                             VALUES ('$user_id', '$farmer_id', '$crop_id', '$msg_text')");
                             
        header("Location: chat.php?farmer_id=$farmer_id&buyer_id=$buyer_id&crop_id=$crop_id");
        exit();
    }
}

// 3. Respond to bargain (Accept / Reject)
if (isset($_POST['respond_bargain'])) {
    $bargain_id = mysqli_real_escape_string($conn, $_POST['bargain_id']);
    $action = mysqli_real_escape_string($conn, $_POST['bargain_action']); // 'accepted' or 'rejected'
    
    if (($action == 'accepted' || $action == 'rejected') && !empty($bargain_id)) {
        // Update bargain status
        mysqli_query($conn, "UPDATE bargains SET status='$action' WHERE id='$bargain_id' AND farmer_id='$user_id'");
        
        // Fetch bargain details to send an automatic chat alert
        $bargain_res = mysqli_query($conn, "SELECT proposed_price FROM bargains WHERE id='$bargain_id'");
        if ($b_row = mysqli_fetch_assoc($bargain_res)) {
            $p_price = $b_row['proposed_price'];
            if ($action == 'accepted') {
                $msg_text = "<i class='ph-duotone ph-check-circle'></i> Bargain Accepted! I have agreed to your negotiated rate of ₹{$p_price}/kg. You can checkout now at this price! <i class='ph-duotone ph-party-popper'></i>";
            } else {
                $msg_text = "<i class='ph-duotone ph-x-circle'></i> Bargain Declined. Please propose another offer or checkout at the listed standard price.";
            }
            mysqli_query($conn, "INSERT INTO chat_messages (sender_id, receiver_id, crop_id, message) 
                                 VALUES ('$user_id', '$buyer_id', '$crop_id', '$msg_text')");
        }
        
        header("Location: chat.php?farmer_id=$farmer_id&buyer_id=$buyer_id&crop_id=$crop_id");
        exit();
    }
}

// Fetch persistent chat history
$chat_messages = [];
if (!empty($crop_id)) {
    // Both buyer and farmer must be involved in this specific thread
    $messages_query = "SELECT * FROM chat_messages 
                       WHERE crop_id='$crop_id' 
                         AND ((sender_id='$buyer_id' AND receiver_id='$farmer_id') 
                           OR (sender_id='$farmer_id' AND receiver_id='$buyer_id')) 
                       ORDER BY created_at ASC";
    $messages_res = mysqli_query($conn, $messages_query);
    while ($m_row = mysqli_fetch_assoc($messages_res)) {
        $chat_messages[] = $m_row;
    }
}

// Fetch current bargain status
$active_bargain = null;
if (!empty($crop_id)) {
    $bargain_query = "SELECT * FROM bargains WHERE buyer_id='$buyer_id' AND crop_id='$crop_id' LIMIT 1";
    $bargain_res = mysqli_query($conn, $bargain_query);
    if (mysqli_num_rows($bargain_res) > 0) {
        $active_bargain = mysqli_fetch_assoc($bargain_res);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Connection Room | AgroNava</title>
    
    <!-- Link styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=2.0">
    
    <style>
        .chat-app {
            display: grid;
            grid-template-columns: 280px 1fr;
            height: calc(100vh - 80px);
            max-width: 1300px;
            margin: 0 auto;
            background: white;
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
            box-shadow: var(--shadow-md);
        }
        
        .chat-sidebar {
            background: var(--light-bg);
            border-right: 1px solid var(--border);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .chat-window {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: #fafafb;
        }
        
        .chat-header {
            padding: 16px 28px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chat-messages {
            flex: 1;
            padding: 24px 28px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .message-bubble {
            max-width: 65%;
            padding: 12px 18px;
            border-radius: var(--radius-md);
            font-size: 14.5px;
            line-height: 1.5;
            position: relative;
        }
        
        .message-received {
            background: white;
            color: var(--text-main);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .message-sent {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15);
        }
        
        .chat-footer {
            padding: 16px 28px;
            background: white;
            border-top: 1px solid var(--border);
        }
        
        .chat-input-form {
            display: flex;
            gap: 12px;
        }
        
        .chat-input {
            flex: 1;
            padding: 14px 18px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            outline: none;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .chat-input:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-glow);
        }
        
        .sidebar-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .sidebar-item.active {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
            background: var(--primary-light);
        }
        
        @media (max-width: 768px) {
            .chat-app {
                grid-template-columns: 1fr;
            }
            .chat-sidebar {
                display: none;
            }
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body style="background: #f1f5f9; min-height: 100vh; overflow: hidden;">

    <!-- Navbar -->
    <header class="navbar" style="position: relative;">
        <a href="index.php" class="navbar-brand">
            <span><i class='ph-duotone ph-plant'></i></span> AgroNava
        </a>
        <button class="navbar-toggle" id="navbar-toggle-btn" aria-label="Toggle navigation">
            <span>☰</span>
        </button>
        <div class="navbar-menu" id="navbar-menu-container">
            <?php if($role == 'farmer') { ?>
                <a href="farmer/dashboard.php" style="color: var(--text-muted); font-weight: 600;">My Listings</a>
                <a href="farmer/orders.php" style="color: var(--text-muted); font-weight: 600;">Manage Orders</a>
            <?php } else { ?>
                <a href="buyer/marketplace.php" style="color: var(--text-muted); font-weight: 600;">Marketplace</a>
                <a href="buyer/my_orders.php" style="color: var(--text-muted); font-weight: 600;">My Orders</a>
            <?php } ?>
            
            <!-- Glowing Notification Bell -->
            <div class="notif-bell-container" id="notif-bell-btn">
                <span class="notif-bell-icon"><i class='ph-duotone ph-bell'></i></span>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
                
                <!-- Notification Dropdown -->
                <div class="notif-dropdown" id="notif-dropdown-menu" style="top: 55px;">
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
                                echo get_notification_html($notif);
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
                <span>💬</span> Connection Room
            </div>
        </div>
    </header>

    <div class="chat-app">
        
        <!-- Sidebar thread listings -->
        <div class="chat-sidebar">
            <h3 style="font-size: 16px; color: var(--dark); margin-bottom: 6px;">Active Connection</h3>
            
            <div class="sidebar-item active">
                <span style="font-size: 24px;"><?php echo ($role == 'buyer') ? '👨‍<i class="ph-duotone ph-plant"></i>' : '<i class="ph-duotone ph-shopping-cart"></i>'; ?></span>
                <div style="flex: 1; min-width: 0;">
                    <h4 style="font-size: 13.5px; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo htmlspecialchars($other_name); ?>
                    </h4>
                    <p style="font-size: 11px; color: var(--text-muted); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        Crop: <?php echo htmlspecialchars($crop_name); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Active Messaging Console -->
        <div class="chat-window">
            
            <div class="chat-header">
                <div>
                    <h3 style="font-size: 16px; color: var(--dark); margin: 0;">
                        🟢 Bargaining Chat with <?php echo htmlspecialchars($other_name); ?>
                    </h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 0;">
                        Discussing: <strong style="color: var(--secondary);"><?php echo htmlspecialchars($crop_name); ?></strong> @ ₹<?php echo $crop_price; ?>/kg
                    </p>
                </div>
                
                <?php if($role == 'buyer') { ?>
                    <a href="buyer/marketplace.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                        ← Exit Chat
                    </a>
                <?php } else { ?>
                    <a href="farmer/dashboard.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                        ← Exit Chat
                    </a>
                <?php } ?>
            </div>
            
            <!-- Dynamic Bargaining Panel (Wow Golden UI!) -->
            <div style="padding: 16px 28px 0 28px;">
                <?php if ($role == 'buyer') { ?>
                    <?php if (!$active_bargain) { ?>
                        <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2); padding: 16px; border-radius: var(--radius-md);">
                            <h4 style="color: #d97706; margin: 0 0 6px 0; font-size: 14px; display: flex; align-items: center; gap: 6px;">💡 Propose a Price Bargain</h4>
                            <p style="font-size: 11.5px; color: var(--text-muted); margin: 0 0 12px 0;">Offer a custom price. The grower will be notified and can instantly approve your proposal.</p>
                            <form method="POST" style="display: flex; gap: 10px;">
                                <input type="number" name="proposed_price" class="form-control" placeholder="Offer price (₹/kg)" min="1" max="<?php echo $crop_price; ?>" required style="max-width: 180px; padding: 8px 12px; font-size: 13px;">
                                <button type="submit" name="propose_bargain" class="btn btn-primary" style="background: #d97706; border-color: #d97706; padding: 8px 16px; font-size: 12.5px;">Propose Offer <i class='ph-duotone ph-handshake'></i></button>
                            </form>
                        </div>
                    <?php } else if ($active_bargain['status'] == 'pending') { ?>
                        <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); padding: 14px; border-radius: var(--radius-md);">
                            <h4 style="color: #d97706; margin: 0 0 4px 0; font-size: 14px; display: flex; align-items: center; gap: 6px;">⌛ Bargain Pending Review</h4>
                            <p style="font-size: 12.5px; color: var(--text-main); margin: 0;">You proposed a rate of <strong>₹<?php echo $active_bargain['proposed_price']; ?>/kg</strong> (Listed: ₹<?php echo $crop_price; ?>/kg). Awaiting response from <?php echo htmlspecialchars($farmer_name); ?>.</p>
                        </div>
                    <?php } else if ($active_bargain['status'] == 'accepted') { ?>
                        <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); padding: 14px; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <h4 style="color: var(--primary-hover); margin: 0 0 4px 0; font-size: 14px; display: flex; align-items: center; gap: 6px;"><i class='ph-duotone ph-party-popper'></i> Bargain Confirmed!</h4>
                                <p style="font-size: 12.5px; color: var(--text-main); margin: 0;">Farmer accepted your offer of <strong>₹<?php echo $active_bargain['proposed_price']; ?>/kg</strong>! Direct checkout is active.</p>
                            </div>
                            <a href="buyer/marketplace.php" class="btn btn-primary" style="padding: 10px 18px; font-size: 13px;"><i class='ph-duotone ph-shopping-cart'></i> Checkout Now</a>
                        </div>
                    <?php } else if ($active_bargain['status'] == 'rejected') { ?>
                        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); padding: 14px; border-radius: var(--radius-md);">
                            <h4 style="color: var(--danger); margin: 0 0 4px 0; font-size: 14px; display: flex; align-items: center; gap: 6px;"><i class='ph-duotone ph-x-circle'></i> Bargain Offer Declined</h4>
                            <p style="font-size: 12.5px; color: var(--text-main); margin: 0 0 10px 0;">Offer of ₹<?php echo $active_bargain['proposed_price']; ?>/kg was declined. Submit a revised deal below:</p>
                            <form method="POST" style="display: flex; gap: 10px;">
                                <input type="number" name="proposed_price" class="form-control" placeholder="New price offer (₹/kg)" min="1" max="<?php echo $crop_price; ?>" required style="max-width: 180px; padding: 8px 12px; font-size: 13px;">
                                <button type="submit" name="propose_bargain" class="btn btn-primary" style="background: #d97706; border-color: #d97706; padding: 8px 16px; font-size: 12.5px;">Resubmit Offer <i class='ph-duotone ph-handshake'></i></button>
                            </form>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <!-- Farmer's view of active bargains -->
                    <?php if ($active_bargain && $active_bargain['status'] == 'pending') { ?>
                        <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), rgba(16, 185, 129, 0.08)); border: 2px dashed #d97706; padding: 16px; border-radius: var(--radius-md);">
                            <h4 style="color: #d97706; margin: 0 0 6px 0; font-size: 14.5px; display: flex; align-items: center; gap: 6px;">💡 Price Negotiation Offer Received!</h4>
                            <p style="font-size: 13px; color: var(--text-main); margin: 0 0 12px 0;">
                                Buyer <strong><?php echo htmlspecialchars($buyer_name); ?></strong> proposed a rate of <strong>₹<?php echo $active_bargain['proposed_price']; ?>/kg</strong> (Your price: ₹<?php echo $crop_price; ?>/kg).
                            </p>
                            <form method="POST" style="display: flex; gap: 12px; align-items: center;">
                                <input type="hidden" name="bargain_id" value="<?php echo $active_bargain['id']; ?>">
                                <input type="hidden" name="bargain_action" id="bargain-action-val" value="accepted">
                                <button type="submit" name="respond_bargain" id="btn-accept" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px; background: var(--primary-hover);">Accept Deal <i class='ph-duotone ph-check-circle'></i></button>
                                <button type="submit" name="respond_bargain" id="btn-reject" class="btn btn-danger" style="padding: 8px 16px; font-size: 13px;">Decline Offer <i class='ph-duotone ph-x-circle'></i></button>
                            </form>
                            <script>
                                document.getElementById('btn-accept').addEventListener('click', () => {
                                    document.getElementById('bargain-action-val').value = 'accepted';
                                });
                                document.getElementById('btn-reject').addEventListener('click', () => {
                                    document.getElementById('bargain-action-val').value = 'rejected';
                                });
                            </script>
                        </div>
                    <?php } else if ($active_bargain && $active_bargain['status'] == 'accepted') { ?>
                        <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); padding: 14px; border-radius: var(--radius-md);">
                            <h4 style="color: var(--primary-hover); margin: 0 0 4px 0; font-size: 14px;"><i class='ph-duotone ph-check-circle'></i> Negotiated Price Active</h4>
                            <p style="font-size: 12.5px; color: var(--text-main); margin: 0;">You agreed to a direct rate of <strong>₹<?php echo $active_bargain['proposed_price']; ?>/kg</strong> for this user. Awaiting purchase order.</p>
                        </div>
                    <?php } else if ($active_bargain && $active_bargain['status'] == 'rejected') { ?>
                        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); padding: 14px; border-radius: var(--radius-md);">
                            <h4 style="color: var(--danger); margin: 0 0 4px 0; font-size: 14px;"><i class='ph-duotone ph-x-circle'></i> Offer Declined</h4>
                            <p style="font-size: 12.5px; color: var(--text-main); margin: 0;">You declined the buyer's offer of ₹<?php echo $active_bargain['proposed_price']; ?>/kg. Awaiting new offer.</p>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
            
            <!-- Message List -->
            <div class="chat-messages" id="chat-messages-container">
                
                <!-- AgroBot Greeting when chat is empty -->
                <?php if (empty($chat_messages)) { ?>
                    <div class="message-bubble message-received" style="border-left: 3px solid #22c55e; background: linear-gradient(135deg, #f0fdf4, #fff);">
                        <span style="font-size: 10px; color: #16a34a; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;"><i class='ph-duotone ph-robot'></i> AgroBot (Farmer Assistant)</span>
                        🙏 Namaste Ji! Welcome to the Direct Trade Connection for <strong><?php echo htmlspecialchars($crop_name); ?></strong>.
                        Our farm-fresh produce is listed at <strong>₹<?php echo $crop_price; ?>/kg</strong>.
                        You can ask me about <strong>price, quality, delivery, payment terms</strong> or make a bargain offer using the panel above.
                        Looking forward to doing great business with you! <i class='ph-duotone ph-plant'></i>
                    </div>
                <?php } else { ?>
                    <?php foreach ($chat_messages as $msg) { 
                        $bubble_class = ($msg['sender_id'] == $user_id) ? 'message-sent' : 'message-received';
                    ?>
                        <div class="message-bubble <?php echo $bubble_class; ?>">
                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            <span style="display: block; font-size: 9px; opacity: 0.75; text-align: right; margin-top: 4px;">
                                <?php echo date("h:i A", strtotime($msg['created_at'])); ?>
                            </span>
                        </div>
                    <?php } ?>
                <?php } ?>
                
            </div>
            
            <!-- Message Input form -->
            <div class="chat-footer">
                <form method="POST" class="chat-input-form">
                    <input type="text" class="chat-input" name="message" placeholder="Type your message here..." required autocomplete="off">
                    <button type="submit" name="send_message" class="btn btn-primary" style="padding: 12px 24px;">Send <i class='ph-duotone ph-lightning'></i></button>
                </form>
            </div>
            
        </div>
        
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Scroll to the bottom of the chat window instantly
        const container = document.getElementById("chat-messages-container");
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    </script>
</body>
</html>
