<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../config/db.php");

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == "farmer") {
        header("Location: ../farmer/dashboard.php");
        exit();
    } else if ($_SESSION['role'] == "buyer") {
        header("Location: ../buyer/dashboard.php");
        exit();
    }
}

$message = "";

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    
    // Execute secure credential search
    $sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Smart role-based routing — works from any login page
        if ($user['role'] === 'farmer') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = 'f. ' . $user['name'];
            $_SESSION['role'] = 'farmer';
            header("Location: ../farmer/dashboard.php");
            exit();
        } else {
            // Buyer — establish session and go to buyer dashboard
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = 'b. ' . $user['name'];
            $_SESSION['role'] = 'buyer';
            header("Location: ../buyer/dashboard.php");
            exit();
        }
    } else {
        $message = "<i class='ph-duotone ph-lock-key'></i> Incorrect email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Marketplace Access | AgroNava</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@800;900&family=Inter:wght@400;600&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --cyan: #0891b2;
            --dark-cyan: #0f172a;
            --bg-color: #ecfeff;
            --accent-indigo: #4f46e5;
        }

        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            background: var(--bg-color);
            background-image: 
                linear-gradient(rgba(8, 145, 178, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(8, 145, 178, 0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Top Brand Header */
        .header {
            position: absolute;
            top: 40px;
            right: 5vw;
            z-index: 10;
            text-align: right;
        }

        .logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            text-decoration: none;
            color: #0f172a;
            letter-spacing: 2px;
            text-shadow: 0 0 20px rgba(8, 145, 178, 0.15);
        }

        .back-link {
            display: inline-block;
            margin-top: 10px;
            font-size: 11px;
            color: var(--cyan);
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 1px solid var(--cyan);
            padding-bottom: 2px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--dark-cyan);
            border-color: var(--dark-cyan);
        }

        /* Vertical Massive Text */
        .vertical-text {
            position: absolute;
            left: 3vw;
            top: 50%;
            transform: translateY(-50%) rotate(180deg);
            writing-mode: vertical-rl;
            font-family: 'Outfit', sans-serif;
            font-size: 12vh;
            font-weight: 900;
            color: transparent;
            -webkit-text-stroke: 1px rgba(8, 145, 178, 0.08);
            text-transform: uppercase;
            white-space: nowrap;
            user-select: none;
            z-index: 0;
        }

        /* Center Cyber Console */
        .cyber-console {
            position: relative;
            z-index: 10;
            width: 400px;
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(8, 145, 178, 0.25);
            padding: 50px 40px;
            backdrop-filter: blur(15px);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.08), inset 0 0 20px rgba(8, 145, 178, 0.02);
            clip-path: polygon(0 0, 100% 0, 100% calc(100% - 30px), calc(100% - 30px) 100%, 0 100%);
        }

        .cyber-console::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 40%; height: 2px;
            background: var(--cyan);
            box-shadow: 0 0 15px var(--cyan);
        }

        .console-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .console-title::before {
            content: '';
            display: inline-block;
            width: 10px; height: 10px;
            background: var(--cyan);
            box-shadow: 0 0 10px var(--cyan);
            animation: pulse 2s infinite;
        }

        .alert-box {
            background: rgba(220, 38, 38, 0.08);
            border-left: 2px solid #ef4444;
            color: #b91c1c;
            padding: 12px 15px;
            margin-bottom: 25px;
            font-size: 13px;
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: 1px;
        }

        /* Cyber Inputs */
        .input-group {
            margin-bottom: 30px;
            position: relative;
        }

        .cyber-label {
            display: block;
            font-size: 10px;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--cyan);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .cyber-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #cbd5e1;
            border-left: 3px solid var(--cyan);
            padding: 15px;
            color: #0f172a;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            letter-spacing: 2px;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .cyber-input:focus {
            outline: none;
            background: #ffffff;
            border-color: var(--cyan);
            box-shadow: 0 0 20px rgba(8, 145, 178, 0.12);
        }

        .submit-cyber {
            width: 100%;
            background: transparent;
            border: 1px solid var(--cyan);
            color: var(--cyan);
            padding: 16px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow: hidden;
            margin-top: 10px;
        }

        .submit-cyber::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: var(--cyan);
            transition: all 0.4s ease;
            z-index: -1;
        }

        .submit-cyber:hover {
            color: #fff;
            box-shadow: 0 0 30px rgba(8, 145, 178, 0.3);
        }

        .submit-cyber:hover::before {
            left: 0;
        }

        /* Floating QR Action Hexagon */
        .qr-hex {
            position: absolute;
            bottom: -50px;
            right: 40px;
            width: 100px;
            height: 100px;
            background: #ffffff;
            border: 2px solid var(--cyan);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.4s ease;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            z-index: 20;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .qr-hex:hover {
            background: rgba(8, 145, 178, 0.08);
            box-shadow: 0 0 40px rgba(8, 145, 178, 0.2);
            transform: scale(1.1) rotate(90deg);
        }

        .qr-hex img {
            width: 60px;
            height: 60px;
            transition: all 0.4s;
        }

        .qr-hex:hover img {
            transform: rotate(-90deg);
        }

        .qr-label {
            position: absolute;
            top: -25px;
            right: 120px;
            color: var(--cyan);
            font-size: 10px;
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: 2px;
            white-space: nowrap;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .qr-hex:hover + .qr-label {
            opacity: 1;
            right: 130px;
        }

        /* Quick fill floating link */
        .quick-fill {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: 1px;
            color: #64748b;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .quick-fill:hover {
            color: var(--cyan);
        }

        @keyframes pulse {
            0% { opacity: 0.5; box-shadow: 0 0 5px var(--cyan); }
            50% { opacity: 1; box-shadow: 0 0 20px var(--cyan); }
            100% { opacity: 0.5; box-shadow: 0 0 5px var(--cyan); }
        }

        @media (max-width: 900px) {
            .vertical-text {
                display: none;
            }
            .header {
                right: auto;
                left: 5vw;
                text-align: left;
            }
            .cyber-console {
                width: 85vw;
                padding: 40px 25px;
            }
            .qr-hex {
                right: 20px;
                bottom: -40px;
                width: 80px; height: 80px;
            }
            .qr-hex img { width: 45px; height: 45px; }
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <div class="vertical-text">MARKET ENTRY</div>

    <div class="header">
        <a href="../index.php" class="logo">AGRONAVA</a>
        <br>
        <a href="login.php" class="back-link">⟵ Cancel Protocol</a>
    </div>

    <div class="cyber-console">
        <h1 class="console-title">System Uplink</h1>

        <?php if($message != "") { ?>
            <div class="alert-box"><?php echo $message; ?></div>
        <?php } ?>

        <form method="POST">
            <div class="input-group">
                <label class="cyber-label">Entity Identifier</label>
                <input class="cyber-input" type="email" id="email" name="email" placeholder="BUYER@NODE" required>
            </div>
            
            <div class="input-group">
                <label class="cyber-label">Security Protocol</label>
                <input class="cyber-input" type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            
            <button type="submit" name="login" class="submit-cyber">Execute Handshake</button>
            <div class="quick-fill" onclick="fillTest()">[ Inject Test Profile: aniket@buyer.com ]</div>
        </form>

        <a href="qr_login.php?role=buyer" class="qr-hex">
            <?php
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $base_url = $protocol . $host;
            if (strpos($_SERVER['REQUEST_URI'], '/farm_portal/') !== false) {
                $base_url .= '/farm_portal/';
            } else {
                $base_url .= '/';
            }
            $qr_url = $base_url . "auth/qr_login.php?role=buyer";
            $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&color=0891b2&bgcolor=ffffff&data=" . urlencode($qr_url);
            ?>
            <img src="<?php echo $qr_api; ?>" alt="QR">
        </a>
        <div class="qr-label">SCAN TO OVERRIDE AUTHENTICATION</div>
    </div>

    <script>
        function fillTest() {
            document.getElementById('email').value = 'aniket@buyer.com';
            document.getElementById('password').value = 'password123';
        }
    </script>
</body>
</html>
