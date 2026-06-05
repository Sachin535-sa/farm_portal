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
        
        // Smart role-based routing — no access denied, just send to correct dashboard
        if ($user['role'] === 'buyer') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = 'b. ' . $user['name'];
            $_SESSION['role'] = 'buyer';
            header("Location: ../buyer/dashboard.php");
            exit();
        } else {
            // Farmer — establish session and go to farmer dashboard
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = 'f. ' . $user['name'];
            $_SESSION['role'] = 'farmer';
            header("Location: ../farmer/dashboard.php");
            exit();
        }
    } else {
        $message = "🔒 Incorrect email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grower Authentication | AgroNava</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@800;900&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --gold: #b45309;
        }

        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #fffbeb url('../assets/images/farm_background.png') no-repeat center center;
            background-size: cover;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            overflow: hidden;
        }

        /* Ambient light fade over the image */
        .ambient-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to right, rgba(255, 251, 235, 0.92) 0%, rgba(255, 251, 235, 0.7) 50%, rgba(255, 255, 255, 0.25) 100%);
            backdrop-filter: blur(2px);
            z-index: 1;
        }

        /* Top Brand Header */
        .header {
            position: absolute;
            top: 40px;
            left: 5vw;
            z-index: 10;
        }

        .logo {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 900;
            text-decoration: none;
            color: #0f172a;
            letter-spacing: -1px;
        }

        .back-link {
            display: inline-block;
            margin-top: 10px;
            font-size: 13px;
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--gold);
            padding-bottom: 2px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #0f172a;
            border-color: #0f172a;
        }

        /* Massive Asymmetrical Interface Panel */
        .hud-panel {
            position: absolute;
            bottom: 10vh;
            left: 5vw;
            z-index: 10;
            width: 450px;
        }

        .hud-title {
            font-family: 'Outfit', sans-serif;
            font-size: 70px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 40px;
            text-transform: uppercase;
            color: transparent;
            -webkit-text-stroke: 1.5px #0f172a;
        }

        .hud-title span {
            display: block;
            color: var(--gold);
            -webkit-text-stroke: 0px;
            text-shadow: 0 0 40px rgba(180, 83, 9, 0.15);
        }

        .alert-box {
            background: rgba(239, 68, 68, 0.08);
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            padding: 15px 20px;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 14px;
            border-radius: 0 8px 8px 0;
        }

        /* Borderless Baseline Inputs */
        .input-group {
            margin-bottom: 35px;
            position: relative;
        }

        .input-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #475569;
            margin-bottom: 10px;
            font-weight: 600;
            transition: color 0.3s;
        }

        .baseline-input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 2px solid #cbd5e1;
            padding: 10px 0;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .baseline-input::placeholder {
            color: #94a3b8;
        }

        .baseline-input:focus {
            outline: none;
            border-bottom-color: var(--gold);
            box-shadow: 0 20px 20px -20px rgba(180, 83, 9, 0.25);
        }

        .baseline-input:focus + .input-label,
        .input-group:focus-within .input-label {
            color: var(--gold);
        }

        .submit-hud {
            background: transparent;
            border: 2px solid var(--gold);
            color: var(--gold);
            padding: 18px 40px;
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.4s ease;
            margin-top: 10px;
            border-radius: 8px;
        }

        .submit-hud:hover {
            background: var(--gold);
            color: #fff;
            box-shadow: 0 0 30px rgba(180, 83, 9, 0.3);
            transform: translateX(10px);
        }

        /* Floating QR Action Orb on the Right */
        .qr-orb {
            position: absolute;
            bottom: 10vh;
            right: 5vw;
            z-index: 10;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(180, 83, 9, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.5s ease;
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            text-decoration: none;
        }

        .qr-orb:hover {
            transform: scale(1.1);
            background: rgba(255, 255, 255, 0.85);
            border-color: var(--gold);
            box-shadow: 0 0 50px rgba(180, 83, 9, 0.15);
        }

        .qr-orb img {
            width: 70px;
            height: 70px;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .qr-orb:hover img {
            opacity: 1;
        }

        .qr-label {
            position: absolute;
            right: 140px;
            top: 50%;
            transform: translateY(-50%);
            text-align: right;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 14px;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0;
            transition: all 0.4s ease;
            white-space: nowrap;
        }

        .qr-orb:hover .qr-label {
            opacity: 1;
            right: 150px;
        }

        /* Quick fill floating link */
        .quick-fill {
            position: absolute;
            bottom: -40px;
            left: 0;
            font-size: 12px;
            color: #64748b;
            cursor: pointer;
            transition: color 0.3s;
        }
        .quick-fill:hover {
            color: var(--gold);
        }

        @media (max-width: 900px) {
            .ambient-overlay {
                background: rgba(255, 251, 235, 0.95);
            }
            .hud-panel {
                width: 90vw;
                bottom: 5vh;
            }
            .hud-title {
                font-size: 50px;
            }
            .qr-orb {
                top: 40px;
                right: 5vw;
                bottom: auto;
                width: 60px; height: 60px;
            }
            .qr-orb img {
                width: 35px; height: 35px;
            }
            .qr-orb:hover .qr-label {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="ambient-overlay"></div>

    <div class="header">
        <a href="../index.php" class="logo">AGRONAVA</a>
        <br>
        <a href="login.php" class="back-link">⟵ Change Gateway</a>
    </div>

    <div class="hud-panel">
        <h1 class="hud-title">Grower<br><span>Authorization</span></h1>

        <?php if($message != "") { ?>
            <div class="alert-box"><?php echo $message; ?></div>
        <?php } ?>

        <form method="POST">
            <div class="input-group">
                <label class="input-label">Identity Hash (Email)</label>
                <input class="baseline-input" type="email" id="email" name="email" placeholder="ENTER ADDRESS" required>
            </div>
            
            <div class="input-group">
                <label class="input-label">Access Protocol (Password)</label>
                <input class="baseline-input" type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            
            <button type="submit" name="login" class="submit-hud">Initialize Session</button>
            <div class="quick-fill" onclick="fillTest()">[ Demo Inject: rajesh@farm.com ]</div>
        </form>
    </div>

    <!-- Instant Access QR Orb -->
    <a href="qr_login.php?role=farmer" class="qr-orb">
        <?php
        $qr_url = "http://localhost/farm_portal/auth/qr_login.php?role=farmer";
        $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_url);
        ?>
        <img src="<?php echo $qr_api; ?>" alt="QR">
        <div class="qr-label">Initiate QR Handshake<br><span style="font-size: 11px; color:#475569; font-family:'Inter', sans-serif; font-weight:400; letter-spacing:0;">Scan to bypass manual entry</span></div>
    </a>

    <script>
        function fillTest() {
            document.getElementById('email').value = 'rajesh@farm.com';
            document.getElementById('password').value = 'password123';
        }
    </script>
</body>
</html>
