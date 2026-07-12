<?php
session_start();

// If user is already logged in, redirect them to their respective consoles
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == "farmer") {
        header("Location: ../farmer/dashboard.php");
        exit();
    } else if ($_SESSION['role'] == "buyer") {
        header("Location: ../buyer/dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway | AgroNava</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@800;900&family=Syne:wght@700;800&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --grower-primary: #d97706;
            --grower-bg: #fffbeb;
            --buyer-primary: #0891b2;
            --buyer-bg: #ecfeff;
        }

        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        /* Absolute Brand Logo Header */
        .brand-header {
            position: absolute;
            top: 40px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            text-align: center;
            pointer-events: none;
        }

        .brand-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 900;
            color: #0f172a;
            text-decoration: none;
            letter-spacing: -1px;
            pointer-events: auto;
        }

        .brand-tag {
            font-family: 'Syne', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: #475569;
            opacity: 0.8;
            margin-top: 5px;
        }

        .split-wrapper {
            display: flex;
            width: 100vw;
            height: 100vh;
            position: relative;
        }

        /* Diagonal Panes */
        .pane {
            position: absolute;
            top: 0;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            overflow: hidden;
            transition: all 0.8s cubic-bezier(0.85, 0, 0.15, 1);
        }

        /* GROWER PANE (Left) */
        .pane-grower {
            left: 0;
            width: 60%;
            background: radial-gradient(circle at 30% 50%, rgba(245, 158, 11, 0.08) 0%, var(--grower-bg) 80%);
            clip-path: polygon(0 0, 100% 0, 70% 100%, 0% 100%);
            z-index: 2;
        }

        /* BUYER PANE (Right) */
        .pane-buyer {
            right: 0;
            width: 70%;
            background: radial-gradient(circle at 70% 50%, rgba(6, 182, 212, 0.08) 0%, var(--buyer-bg) 80%);
            clip-path: polygon(30% 0, 100% 0, 100% 100%, 0% 100%);
            z-index: 1;
        }

        /* Hover Expansion Mechanics */
        .pane-grower:hover {
            width: 75%;
            clip-path: polygon(0 0, 100% 0, 80% 100%, 0% 100%);
            background: radial-gradient(circle at 40% 50%, rgba(245, 158, 11, 0.15) 0%, var(--grower-bg) 80%);
            z-index: 3;
        }
        
        .pane-grower:hover ~ .pane-buyer {
            width: 50%;
        }

        .pane-buyer:hover {
            width: 80%;
            clip-path: polygon(20% 0, 100% 0, 100% 100%, 0% 100%);
            background: radial-gradient(circle at 60% 50%, rgba(6, 182, 212, 0.15) 0%, var(--buyer-bg) 80%);
            z-index: 3;
        }

        /* Massive Typography */
        .massive-text {
            font-family: 'Outfit', sans-serif;
            font-size: 10vw;
            font-weight: 900;
            text-transform: uppercase;
            color: transparent;
            -webkit-text-stroke: 2px rgba(15, 23, 42, 0.08);
            transition: all 0.6s ease;
            position: absolute;
            white-space: nowrap;
        }

        .pane-grower .massive-text {
            left: 5vw;
            top: 50%;
            transform: translateY(-50%);
        }

        .pane-buyer .massive-text {
            right: 5vw;
            top: 50%;
            transform: translateY(-50%);
        }

        .pane-grower:hover .massive-text {
            color: var(--grower-primary);
            -webkit-text-stroke: 0px;
            text-shadow: 0 0 60px rgba(245, 158, 11, 0.25);
            transform: translateY(-50%) scale(1.05);
            left: 8vw;
        }

        .pane-buyer:hover .massive-text {
            color: var(--buyer-primary);
            -webkit-text-stroke: 0px;
            text-shadow: 0 0 60px rgba(6, 182, 212, 0.25);
            transform: translateY(-50%) scale(1.05);
            right: 8vw;
        }

        /* Subtitle overlays */
        .sub-overlay {
            position: absolute;
            font-family: 'Inter', sans-serif;
            color: #475569;
            opacity: 0;
            transition: all 0.6s ease;
            max-width: 300px;
            font-size: 15px;
            line-height: 1.6;
        }

        .pane-grower .sub-overlay {
            left: 8vw;
            top: 65%;
            transform: translateY(20px);
        }

        .pane-buyer .sub-overlay {
            right: 8vw;
            top: 65%;
            text-align: right;
            transform: translateY(20px);
        }

        .pane-grower:hover .sub-overlay,
        .pane-buyer:hover .sub-overlay {
            opacity: 0.9;
            transform: translateY(0);
            transition-delay: 0.2s;
        }

        .action-arrow {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .grower-arrow {
            background: rgba(245, 158, 11, 0.1);
            color: var(--grower-primary);
            border: 1px solid var(--grower-primary);
        }

        .buyer-arrow {
            background: rgba(6, 182, 212, 0.1);
            color: var(--buyer-primary);
            border: 1px solid var(--buyer-primary);
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .pane-grower {
                width: 100%;
                height: 55%;
                clip-path: polygon(0 0, 100% 0, 100% 80%, 0 100%);
            }
            .pane-buyer {
                width: 100%;
                height: 60%;
                top: auto;
                bottom: 0;
                clip-path: polygon(0 20%, 100% 0, 100% 100%, 0 100%);
            }
            .massive-text {
                font-size: 18vw;
            }
            .pane-grower:hover, .pane-buyer:hover {
                width: 100%;
            }
            .pane-grower:hover {
                height: 65%;
                clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
            }
            .pane-buyer:hover {
                height: 70%;
                clip-path: polygon(0 10%, 100% 0, 100% 100%, 0 100%);
            }
        }
    </style>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <header class="brand-header">
        <a href="../index.php" class="brand-logo" style="text-decoration: none;">AGRONAVA</a>
        <div class="brand-tag">Select Your Vector</div>
        <div style="margin-top: 12px; pointer-events: auto;">
            <a href="login_delivery.php" style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #64748b; font-weight: 700; background: rgba(0,0,0,0.04); padding: 8px 16px; border-radius: 50px; border: 1px solid rgba(0,0,0,0.08); transition: all 0.3s ease;"><i class='ph-duotone ph-truck'></i> Logistics Gateway</a>
        </div>
    </header>

    <main class="split-wrapper">
        <!-- GROWER PANE -->
        <a href="login_farmer.php" class="pane pane-grower">
            <div class="massive-text">GROW</div>
            <div class="sub-overlay">
                Access the Grower Console. Manage agricultural assets, monitor live price benchmarks, and execute secure wholesale bargains directly.
                <div class="action-arrow grower-arrow">Enter Portal ➔</div>
            </div>
        </a>

        <!-- BUYER PANE -->
        <a href="login_buyer.php" class="pane pane-buyer">
            <div class="massive-text">TRADE</div>
            <div class="sub-overlay">
                Access the Buyer Marketplace. Source certified fresh produce, negotiate directly with farms, and settle instantly via digital QR.
                <div class="action-arrow buyer-arrow">Enter Market ➔</div>
            </div>
        </a>
    </main>

</body>
</html>