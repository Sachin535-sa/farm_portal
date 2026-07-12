<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../config/db.php");

$role = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$redirect_url = "../index.php";
$display_name = "";
$theme_color = "#6366f1";
$glow_color = "rgba(99, 102, 241, 0.5)";
$role_title = "";
$welcome_text = "";
$bg_gradient = "radial-gradient(circle at 50% 50%, #f8fafc 0%, #cbd5e1 100%)";
$text_color = "#0f172a";
$card_bg = "rgba(255, 255, 255, 0.8)";
$card_border = "rgba(15, 23, 42, 0.08)";
$shadow_color = "rgba(15, 23, 42, 0.08)";

if ($role === 'farmer') {
    // Default farmer Rajesh Kumar
    $email = 'rajesh@farm.com';
    $sql = "SELECT * FROM users WHERE email='$email' AND role='farmer'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = 'f. ' . $user['name'];
        $_SESSION['role'] = 'farmer';
        
        $display_name = $user['name'];
        $theme_color = "#f59e0b"; // Sunset Gold
        $glow_color = "rgba(245, 158, 11, 0.6)";
        $role_title = "Grower Console";
        $welcome_text = "<i class='ph-duotone ph-plant'></i> Welcome Back, " . htmlspecialchars($display_name) . "!";
        $bg_gradient = "radial-gradient(circle at 50% 50%, #fffbeb 0%, #fef3c7 100%)";
        $text_color = "#0f172a";
        $card_bg = "rgba(255, 255, 255, 0.85)";
        $card_border = "rgba(245, 158, 11, 0.2)";
        $shadow_color = "rgba(180, 83, 9, 0.1)";
        $redirect_url = "../farmer/dashboard.php";
    }
} else if ($role === 'buyer') {
    // Default buyer Aniket
    $email = 'aniket@buyer.com';
    $sql = "SELECT * FROM users WHERE email='$email' AND role='buyer'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = 'b. ' . $user['name'];
        $_SESSION['role'] = 'buyer';
        
        $display_name = $user['name'];
        $theme_color = "#06b6d4"; // Cyber Cyan
        $glow_color = "rgba(6, 182, 212, 0.6)";
        $role_title = "Buyer Console";
        $welcome_text = "🛍️ Welcome Back, " . htmlspecialchars($display_name) . "!";
        $bg_gradient = "radial-gradient(circle at 50% 50%, #ecfeff 0%, #cffafe 100%)";
        $text_color = "#0f172a";
        $card_bg = "rgba(255, 255, 255, 0.85)";
        $card_border = "rgba(6, 182, 212, 0.2)";
        $shadow_color = "rgba(8, 145, 178, 0.1)";
        $redirect_url = "../buyer/dashboard.php";
    }
} else {
    // Invalid request
    header("Location: ../index.php");
    exit();
}

if (empty($display_name)) {
    // Fallback if user not found in DB
    $message = "<i class='ph-duotone ph-warning'></i> Demo test user not found. Please register an account first!";
    echo "<div style='color: #0f172a; background: #fffbeb; padding: 20px; font-family: sans-serif; text-align: center; border: 1px solid #f59e0b;'>$message<br><a href='register.php' style='color: #06b6d4;'>Go to Registration</a></div>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Securing Access | AgroNava</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@700;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: <?php echo $bg_gradient; ?>;
            color: <?php echo $text_color; ?>;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        .loader-container {
            text-align: center;
            max-width: 420px;
            padding: 40px;
            background: <?php echo $card_bg; ?>;
            border: 1px solid <?php echo $card_border; ?>;
            border-radius: 24px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 20px 50px <?php echo $shadow_color; ?>;
        }

        /* Spinning glowing orb */
        .glowing-orb {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            position: relative;
            border-radius: 50%;
            background: radial-gradient(circle, #fff 0%, <?php echo $theme_color; ?> 70%);
            box-shadow: 0 0 30px <?php echo $theme_color; ?>, 0 0 60px <?php echo $glow_color; ?>;
            animation: pulse-glow 2s ease-in-out infinite alternate, rotate-orb 15s linear infinite;
        }

        .glowing-orb::after {
            content: '';
            position: absolute;
            top: -5px; right: -5px; bottom: -5px; left: -5px;
            border-radius: 50%;
            border: 2px dashed <?php echo $theme_color; ?>;
            opacity: 0.5;
            animation: spin-dashed 6s linear infinite;
        }

        .auth-title {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 12px;
            color: <?php echo $text_color; ?>;
            letter-spacing: -0.5px;
        }

        .auth-subtitle {
            font-size: 14px;
            color: #475569;
            margin-bottom: 24px;
            font-weight: 500;
            line-height: 1.5;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid <?php echo $card_border; ?>;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            color: <?php echo $theme_color; ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @keyframes pulse-glow {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 20px <?php echo $theme_color; ?>, 0 0 40px <?php echo $glow_color; ?>;
            }
            100% {
                transform: scale(1.05);
                box-shadow: 0 0 40px <?php echo $theme_color; ?>, 0 0 80px <?php echo $glow_color; ?>;
            }
        }

        @keyframes rotate-orb {
            100% { transform: rotate(360deg); }
        }

        @keyframes spin-dashed {
            100% { transform: rotate(-360deg); }
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <div class="loader-container">
        <div class="glowing-orb"></div>
        <div class="auth-title"><?php echo $welcome_text; ?></div>
        <div class="auth-subtitle">Initializing secure dynamic session credentials for the <strong><?php echo $role_title; ?></strong>...</div>
        <div class="status-badge">
            <span style="display: inline-block; width: 6px; height: 6px; background: <?php echo $theme_color; ?>; border-radius: 50%; animation: pulse-glow 0.8s infinite alternate;"></span>
            Session Handshake Active
        </div>
    </div>

    <script>
        // Redirect after a spectacular 2-second visual build-up
        setTimeout(function() {
            window.location.href = "<?php echo $redirect_url; ?>";
        }, 1800);
    </script>

</body>
</html>
