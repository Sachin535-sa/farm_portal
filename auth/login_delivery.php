<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../config/db.php");

// If user is already logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == "delivery_partner") {
        header("Location: ../delivery/dashboard.php");
        exit();
    } else if ($_SESSION['role'] == "farmer") {
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
    
    $sql = "SELECT * FROM users WHERE email='$email' AND password='$password' AND role='delivery_partner'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = 'delivery_partner';
        header("Location: ../delivery/dashboard.php");
        exit();
    } else {
        $message = "<i class='ph-duotone ph-lock-key'></i> Incorrect credentials or unauthorized access.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Gateway | AgroNava</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@800;900&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #1e3a8a;
        }

        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #0f172a;
            font-family: 'Inter', sans-serif;
            color: #f1f5f9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hud-panel {
            width: 100%;
            max-width: 440px;
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            text-align: center;
        }

        .hud-title {
            font-family: 'Outfit', sans-serif;
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 24px;
            letter-spacing: -1px;
            color: #38bdf8;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .baseline-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 14px;
            border-radius: 8px;
            color: white;
            box-sizing: border-box;
        }

        .baseline-input:focus {
            border-color: #38bdf8;
            outline: none;
        }

        .submit-hud {
            width: 100%;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: white;
            border: none;
            padding: 16px;
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .submit-hud:hover {
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.3);
            transform: translateY(-2px);
        }

        .quick-fill {
            font-size: 12px;
            color: #64748b;
            cursor: pointer;
            margin-top: 18px;
            display: inline-block;
            transition: color 0.3s;
        }

        .quick-fill:hover {
            color: #38bdf8;
        }

        .alert-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <div class="hud-panel">
        <h1 class="hud-title">Logistics<br><span>Authorization</span></h1>

        <?php if($message != "") { ?>
            <div class="alert-box"><?php echo $message; ?></div>
        <?php } ?>

        <form method="POST">
            <div class="input-group">
                <label class="input-label">Identity (Email)</label>
                <input class="baseline-input" type="email" id="email" name="email" placeholder="ENTER ADDRESS" required>
            </div>
            
            <div class="input-group">
                <label class="input-label">Access Protocol (Password)</label>
                <input class="baseline-input" type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            
            <button type="submit" name="login" class="submit-hud">Initialize Terminal</button>
            <div class="quick-fill" onclick="fillTest()">[ Demo Inject: vijay@delivery.com ]</div>
        </form>
    </div>

    <script>
        function fillTest() {
            document.getElementById('email').value = 'vijay@delivery.com';
            document.getElementById('password').value = 'password123';
        }
    </script>

</body>
</html>
