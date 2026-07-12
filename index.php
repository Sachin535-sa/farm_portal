<?php
session_start();
include("config/db.php");

$is_logged_in = isset($_SESSION['user_id']);
$role = $is_logged_in ? $_SESSION['role'] : 'guest';
$name = $is_logged_in ? $_SESSION['name'] : '';

// Fetch active crops from database to populate the Mandi Live Ticker
$crops_query = mysqli_query($conn, "SELECT c.*, u.name as farmer_name, u.distributor_badge, u.distributor_score 
                                    FROM crops c 
                                    JOIN users u ON c.farmer_id = u.id 
                                    ORDER BY c.id DESC LIMIT 4");

$db_crops = [];
if ($crops_query && mysqli_num_rows($crops_query) > 0) {
    while ($row = mysqli_fetch_assoc($crops_query)) {
        $db_crops[] = $row;
    }
}

// High-fidelity fallback crops if the database is currently empty
if (empty($db_crops)) {
    $db_crops = [
        [
            'crop_name' => 'Premium Sharbati Wheat (Kanak)',
            'price' => 28,
            'quantity' => 800,
            'rating_avg' => 4.85,
            'review_count' => 36,
            'sales_volume' => 520,
            'farmer_name' => 'Gurpreet Singh',
            'distributor_badge' => '🌟 Highly Rated Eco-Grower',
            'distributor_score' => 95,
            'sustainability_badges' => 'Eco-Grown, Zero Chemical'
        ],
        [
            'crop_name' => 'Organic Basmati Paddy (Rice)',
            'price' => 65,
            'quantity' => 450,
            'rating_avg' => 4.90,
            'review_count' => 28,
            'sales_volume' => 380,
            'farmer_name' => 'Rajesh Kumar',
            'distributor_badge' => '🥇 Certified Top-Tier Quality Distributor',
            'distributor_score' => 98,
            'sustainability_badges' => 'Grade-A Basmati, Verified Origin'
        ],
        [
            'crop_name' => 'Kufri Jyoti Potatoes (Grade-A)',
            'price' => 22,
            'quantity' => 600,
            'rating_avg' => 4.60,
            'review_count' => 14,
            'sales_volume' => 190,
            'farmer_name' => 'Rajesh Kumar',
            'distributor_badge' => '🥇 Certified Top-Tier Quality Distributor',
            'distributor_score' => 98,
            'sustainability_badges' => 'Sortex Cleansed'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroNava | Direct Agricultural Trade Terminal 🌾</title>
    
    <!-- Design styling system & Google Fonts -->
    <link rel="stylesheet" href="assets/css/style.css?v=1.6">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Syne:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ── Elite Brand Landing Aesthetics ── */
        :root {
            --grower-glow: rgba(245, 158, 11, 0.4);
            --buyer-glow: rgba(6, 182, 212, 0.4);
            --emerald-glow: rgba(16, 185, 129, 0.35);
            --dark-hero-bg: #090e14;
        }

        body {
            background-color: var(--dark-hero-bg);
            color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        /* ── Advanced Ambient Animated Background ── */
        .ambient-bg {
            position: fixed;
            inset: 0;
            z-index: -2;
            background: radial-gradient(circle at 10% 20%, #0c171f 0%, #06090d 100%);
            overflow: hidden;
        }

        .ambient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.15;
            z-index: -1;
            pointer-events: none;
            animation: orbDrift 20s infinite alternate ease-in-out;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: #10b981;
            top: -10%;
            left: -10%;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 600px;
            height: 600px;
            background: #06b6d4;
            bottom: -15%;
            right: -10%;
            animation-delay: 5s;
        }

        .orb-3 {
            width: 400px;
            height: 400px;
            background: #f59e0b;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: 10s;
        }

        @keyframes orbDrift {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -50px) scale(1.15); }
            100% { transform: translate(-30px, 30px) scale(0.9); }
        }

        /* Ambient Sprout Particle Layers */
        .floating-sprout {
            position: absolute;
            font-size: 24px;
            opacity: 0.12;
            pointer-events: none;
            animation: floatSprout 12s infinite linear;
        }

        @keyframes floatSprout {
            0% { transform: translateY(110vh) rotate(0deg) translateX(0); opacity: 0; }
            10% { opacity: 0.15; }
            90% { opacity: 0.15; }
            100% { transform: translateY(-10vh) rotate(360deg) translateX(50px); opacity: 0; }
        }

        /* ── Dynamic Transparent Navigation ── */
        .navbar-landing {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 60px;
            background: rgba(9, 14, 20, 0.7);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.4s ease;
        }

        .navbar-landing.scrolled {
            padding: 14px 60px;
            background: rgba(9, 14, 20, 0.9);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand-landing {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .navbar-brand-landing span.brand-glow {
            background: linear-gradient(135deg, #10b981, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
        }

        .navbar-links {
            display: flex;
            gap: 32px;
            align-items: center;
        }

        .navbar-links a {
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            position: relative;
            transition: color 0.3s;
        }

        .navbar-links a:hover {
            color: #fff;
        }

        .navbar-links a::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #10b981, #06b6d4);
            transition: width 0.3s;
        }

        .navbar-links a:hover::after {
            width: 100%;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* ── Spectacular Hero Splash ── */
        .hero-section {
            min-height: 92vh;
            display: flex;
            align-items: center;
            position: relative;
            padding: 80px 60px;
        }

        .hero-layout {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            max-width: 1350px;
            margin: 0 auto;
            align-items: center;
            gap: 60px;
            width: 100%;
        }

        .badge-premium {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.1);
        }

        .hero-title-main {
            font-family: 'Outfit', sans-serif;
            font-size: 72px;
            line-height: 1.05;
            font-weight: 900;
            margin-bottom: 24px;
            color: #fff;
            letter-spacing: -2px;
        }

        .hero-title-main span.gradient-text-1 {
            background: linear-gradient(135deg, #fff 30%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-title-main span.gradient-text-2 {
            background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            display: block;
            margin-top: 8px;
        }

        .hero-desc-main {
            font-size: 18px;
            color: #94a3b8;
            line-height: 1.7;
            margin-bottom: 40px;
            max-width: 580px;
        }

        /* Dual CTA Cards (Splits Farmers & Buyers on index) */
        .gateway-card-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 48px;
        }

        .gateway-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-md);
            padding: 24px;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 190px;
        }

        .gateway-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, var(--card-glow-color), transparent 60%);
            opacity: 0;
            transition: opacity 0.4s;
            z-index: 0;
        }

        .gateway-card:hover {
            transform: translateY(-6px);
            border-color: var(--card-border-color);
            box-shadow: 0 12px 30px var(--card-shadow-color);
        }

        .gateway-card:hover::before {
            opacity: 1;
        }

        .gateway-card-grower {
            --card-glow-color: rgba(245, 158, 11, 0.12);
            --card-border-color: rgba(245, 158, 11, 0.3);
            --card-shadow-color: rgba(245, 158, 11, 0.15);
        }

        .gateway-card-buyer {
            --card-glow-color: rgba(6, 182, 212, 0.12);
            --card-border-color: rgba(6, 182, 212, 0.3);
            --card-shadow-color: rgba(6, 182, 212, 0.15);
        }

        .gateway-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1;
        }

        .gateway-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .gateway-card-grower .gateway-card-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .gateway-card-buyer .gateway-card-icon {
            background: rgba(6, 182, 212, 0.1);
            color: #06b6d4;
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        .gateway-card-badge {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 10px;
            border-radius: 50px;
        }

        .gateway-card-grower .gateway-card-badge {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .gateway-card-buyer .gateway-card-badge {
            background: rgba(6, 182, 212, 0.1);
            color: #06b6d4;
        }

        .gateway-card-content {
            margin-top: 16px;
            z-index: 1;
        }

        .gateway-card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }

        .gateway-card-desc {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
        }

        .gateway-card-footer {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 16px;
            z-index: 1;
            transition: gap 0.3s;
        }

        .gateway-card-grower .gateway-card-footer { color: #f59e0b; }
        .gateway-card-buyer .gateway-card-footer { color: #06b6d4; }

        .gateway-card:hover .gateway-card-footer {
            gap: 12px;
        }

        /* Hero Right-Side HUD Concept Visuals */
        .hero-hud-wrapper {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hud-circle-ring {
            width: 480px;
            height: 480px;
            border-radius: 50%;
            border: 2px dashed rgba(16, 185, 129, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: rotateHUD 40s linear infinite;
        }

        @keyframes rotateHUD {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .hud-inner-glow {
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, transparent 70%);
            z-index: 0;
        }

        .floating-dashboard-preview {
            position: absolute;
            width: 380px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(16, 185, 129, 0.1);
            backdrop-filter: blur(15px);
            padding: 24px;
            z-index: 5;
            animation: floatHUDCard 6s ease-in-out infinite;
        }

        @keyframes floatHUDCard {
            0%, 100% { transform: translateY(0px) rotate(-1deg); }
            50% { transform: translateY(-15px) rotate(1deg); }
        }

        .hud-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .hud-avatar {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hud-avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .hud-title {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
        }

        .hud-subtitle {
            font-size: 10px;
            color: #64748b;
        }

        .hud-badge {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
        }

        .hud-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.04);
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .hud-row-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hud-row-icon {
            color: #f59e0b;
        }

        .hud-row-val {
            font-weight: 700;
            color: #fff;
        }

        .hud-floating-badge {
            position: absolute;
            background: rgba(6, 182, 212, 0.9);
            color: white;
            backdrop-filter: blur(10px);
            padding: 10px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 20px rgba(6, 182, 212, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: floatBadge 6s ease-in-out infinite;
            animation-delay: 3s;
            z-index: 6;
        }

        .grower-badge-floating {
            top: 15%;
            left: -5%;
        }

        .buyer-badge-floating {
            bottom: 15%;
            right: -5%;
            background: rgba(245, 158, 11, 0.9);
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);
        }

        @keyframes floatBadge {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(8px) scale(1.03); }
        }

        /* ── Live Mandi Financial Ticker Card ── */
        .mandi-section {
            padding: 80px 60px;
            max-width: 1350px;
            margin: 0 auto;
            width: 100%;
        }

        .mandi-title-area {
            text-align: center;
            margin-bottom: 50px;
        }

        .mandi-title-area h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 42px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 12px;
        }

        .mandi-title-area p {
            color: #94a3b8;
            max-width: 600px;
            margin: 0 auto;
            font-size: 15px;
        }

        /* Infinite Live Marquee Ticker */
        .marquee-wrapper {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-md);
            overflow: hidden;
            padding: 16px 0;
            margin-bottom: 40px;
            box-shadow: var(--shadow-sm);
        }

        .marquee-content {
            display: flex;
            gap: 40px;
            animation: marquee 25s linear infinite;
            white-space: nowrap;
            width: max-content;
        }

        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .marquee-item {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #e2e8f0;
        }

        .ticker-badge {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }

        .ticker-badge-down {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }

        /* Dynamic Database Crops Grid */
        .crops-grid-landing {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 28px;
            margin-top: 30px;
        }

        .crop-card-landing {
            background: rgba(30, 41, 59, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-md);
            padding: 24px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .crop-card-landing:hover {
            transform: translateY(-5px);
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            background: rgba(30, 41, 59, 0.5);
        }

        .crop-card-badge-origin {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: #94a3b8;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .crop-card-name {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-top: 10px;
            margin-bottom: 8px;
        }

        .crop-card-farmer {
            font-size: 13px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
        }

        .crop-card-price-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 18px;
            margin-top: 16px;
        }

        .crop-card-price-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .crop-card-price {
            font-size: 24px;
            font-weight: 900;
            color: #10b981;
            font-family: 'Outfit', sans-serif;
        }

        .crop-card-volume {
            text-align: right;
            font-size: 12px;
            color: #94a3b8;
        }

        .crop-card-volume span {
            font-weight: 700;
            color: #fff;
        }

        /* ── The Dual Dashboard Separation Spotlight ── */
        .separation-section {
            background: linear-gradient(180deg, rgba(9, 14, 20, 0) 0%, rgba(15, 23, 42, 0.4) 50%, rgba(9, 14, 20, 0) 100%);
            padding: 100px 60px;
        }

        .separation-layout {
            max-width: 1350px;
            margin: 0 auto;
            width: 100%;
        }

        .section-header-separated {
            text-align: center;
            margin-bottom: 70px;
        }

        .section-header-separated h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 42px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 12px;
        }

        .section-header-separated p {
            color: #94a3b8;
            max-width: 700px;
            margin: 0 auto;
            font-size: 15px;
            line-height: 1.6;
        }

        .separation-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .console-preview-card {
            border-radius: var(--radius-lg);
            padding: 40px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 500px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
            transition: all 0.4s;
        }

        .console-preview-card:hover {
            transform: translateY(-5px);
        }

        .farmer-console-card {
            background: linear-gradient(135deg, rgba(20, 15, 10, 0.8) 0%, rgba(40, 25, 10, 0.8) 100%);
            --accent-color: #f59e0b;
        }

        .farmer-console-card:hover {
            border-color: rgba(245, 158, 11, 0.25);
            box-shadow: 0 30px 60px rgba(245, 158, 11, 0.05);
        }

        .buyer-console-card {
            background: linear-gradient(135deg, rgba(10, 15, 25, 0.8) 0%, rgba(10, 30, 45, 0.8) 100%);
            --accent-color: #06b6d4;
        }

        .buyer-console-card:hover {
            border-color: rgba(6, 182, 212, 0.25);
            box-shadow: 0 30px 60px rgba(6, 182, 212, 0.05);
        }

        .console-label-indicator {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--accent-color);
            margin-bottom: 20px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 6px 14px;
            border-radius: 50px;
        }

        .console-title {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 16px;
        }

        .console-desc {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .console-features-list {
            list-style: none;
            margin-bottom: 40px;
        }

        .console-features-list li {
            font-size: 14px;
            color: #cbd5e1;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .console-features-list li span.list-bullet {
            width: 18px;
            height: 18px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: var(--accent-color);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .console-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }

        .farmer-console-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #1e1105;
            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.2);
        }

        .farmer-console-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(245, 158, 11, 0.35);
        }

        .buyer-console-btn {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: #041a1f;
            box-shadow: 0 4px 20px rgba(6, 182, 212, 0.2);
        }

        .buyer-console-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(6, 182, 212, 0.35);
        }

        /* ── Crop Direct Trade Timeline ── */
        .timeline-section {
            padding: 80px 60px;
            max-width: 1350px;
            margin: 0 auto;
            width: 100%;
        }

        .timeline-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            position: relative;
            margin-top: 50px;
        }

        .timeline-grid::after {
            content: '';
            position: absolute;
            top: 40px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.2) 0%, rgba(16, 185, 129, 0.2) 50%, rgba(6, 182, 212, 0.2) 100%);
            z-index: 0;
        }

        .timeline-step {
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .timeline-bubble {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(30, 41, 59, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 24px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .timeline-step:hover .timeline-bubble {
            transform: scale(1.1);
            border-color: var(--step-glow);
            box-shadow: 0 10px 25px var(--step-glow-shadow);
        }

        .step-1 { --step-glow: #f59e0b; --step-glow-shadow: rgba(245, 158, 11, 0.2); }
        .step-2 { --step-glow: #10b981; --step-glow-shadow: rgba(16, 185, 129, 0.2); }
        .step-3 { --step-glow: #0ea5e9; --step-glow-shadow: rgba(14, 165, 233, 0.2); }
        .step-4 { --step-glow: #a855f7; --step-glow-shadow: rgba(168, 85, 247, 0.2); }

        .timeline-step-title {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
        }

        .timeline-step-desc {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
            padding: 0 10px;
        }

        /* ── Platform High-Performance Stats ── */
        .stats-banner {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(6, 182, 212, 0.08) 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 60px 20px;
            margin: 60px 0;
        }

        .stats-banner-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            text-align: center;
        }

        .stat-item-number {
            font-family: 'Outfit', sans-serif;
            font-size: 52px;
            font-weight: 900;
            background: linear-gradient(135deg, #10b981, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 4px;
        }

        .stat-item-label {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #cbd5e1;
        }

        .stat-item-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        /* ── Client Testimonials Slider ── */
        .testimonials-section {
            padding: 80px 60px;
            max-width: 1350px;
            margin: 0 auto;
            width: 100%;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .testimonial-card {
            background: rgba(30, 41, 59, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 32px;
            border-radius: var(--radius-md);
            position: relative;
        }

        .testimonial-quote {
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.6;
            margin-bottom: 24px;
            font-style: italic;
        }

        .testimonial-user {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .testimonial-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .testimonial-username {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
        }

        .testimonial-userrole {
            font-size: 11px;
            color: #64748b;
        }

        /* ── Premium Master Footer ── */
        .footer-landing {
            background: #04070a;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding: 80px 60px 40px;
        }

        .footer-grid {
            max-width: 1350px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.5fr repeat(3, 1fr);
            gap: 60px;
            margin-bottom: 60px;
        }

        .footer-logo-area h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            color: white;
            margin-bottom: 16px;
        }

        .footer-logo-area p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
            max-width: 320px;
        }

        .footer-column h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            color: #fff;
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 12px;
        }

        .footer-column ul li a {
            color: #64748b;
            font-size: 14px;
            transition: color 0.3s;
        }

        .footer-column ul li a:hover {
            color: #10b981;
        }

        .footer-bottom {
            max-width: 1350px;
            margin: 0 auto;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #475569;
        }

        /* ── Responsive Styling ── */
        @media (max-width: 1024px) {
            .hero-layout {
                grid-template-columns: 1fr;
                gap: 50px;
                text-align: center;
            }
            .hero-desc-main {
                margin: 0 auto 40px;
            }
            .gateway-card-container {
                max-width: 600px;
                margin: 0 auto 48px;
            }
            .hero-hud-wrapper {
                display: none;
            }
            .separation-grid {
                grid-template-columns: 1fr;
            }
            .timeline-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 40px;
            }
            .timeline-grid::after {
                display: none;
            }
            .stats-banner-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
            .testimonials-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .navbar-landing {
                padding: 16px 24px;
            }
            .navbar-links {
                display: none;
            }
            .hero-section {
                padding: 60px 24px;
            }
            .hero-title-main {
                font-size: 48px;
            }
            .gateway-card-container {
                grid-template-columns: 1fr;
            }
            .mandi-section, .separation-section, .timeline-section, .testimonials-section {
                padding: 60px 24px;
            }
            .mandi-title-area h2, .section-header-separated h2 {
                font-size: 32px;
            }
            .timeline-grid {
                grid-template-columns: 1fr;
            }
            .stats-banner-container {
                grid-template-columns: 1fr;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .footer-landing {
                padding: 60px 24px 30px;
            }
        }
    </style>
</head>
<body>

    <!-- Ambient Drift Backdrop -->
    <div class="ambient-bg">
        <div class="ambient-orb orb-1"></div>
        <div class="ambient-orb orb-2"></div>
        <div class="ambient-orb orb-3"></div>
        
        <!-- Interactive floating sprouts -->
        <div class="floating-sprout" style="left: 10%; animation-duration: 15s; animation-delay: 0s;">🌱</div>
        <div class="floating-sprout" style="left: 30%; animation-duration: 18s; animation-delay: 2s;">🌾</div>
        <div class="floating-sprout" style="left: 55%; animation-duration: 12s; animation-delay: 5s;">🌱</div>
        <div class="floating-sprout" style="left: 80%; animation-duration: 20s; animation-delay: 1s;">🚜</div>
    </div>

    <!-- Master Header Navigation -->
    <header class="navbar-landing" id="navbar">
        <a href="index.php" class="navbar-brand-landing">
            <span>🌾</span> Agro<span class="brand-glow">Nava</span>
        </a>
        
        <div class="navbar-links">
            <a href="#hero">Direct Terminals</a>
            <a href="#mandi-ticker">Mandi Live Ticker</a>
            <a href="#dual-showcase">Ecosystem Separation</a>
            <a href="#workflow">Direct Journey</a>
            <a href="market_prices.php">MSP Price Indices</a>
            <a href="admin_complaints.php" style="color: #ef4444; font-weight: 700;">🛡️ Dispute Admin</a>
        </div>
        
        <div class="nav-actions">
            <?php if ($is_logged_in) { ?>
                <div style="display: flex; align-items: center; gap: 14px;">
                    <!-- Session customized header node -->
                    <?php if ($role == 'farmer') { ?>
                        <span class="user-badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);">👨‍🌾 <?php echo htmlspecialchars($name); ?> (Grower)</span>
                        <a href="farmer/dashboard.php" class="btn btn-primary" style="background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">Go to Dashboard</a>
                    <?php } else { ?>
                        <span class="user-badge" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.2);">🛒 <?php echo htmlspecialchars($name); ?> (Buyer)</span>
                        <a href="buyer/dashboard.php" class="btn btn-primary" style="background: linear-gradient(135deg, #06b6d4, #0891b2); box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);">Go to Terminal</a>
                    <?php } ?>
                    <a href="auth/logout.php" class="btn btn-secondary" style="border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); color: #94a3b8;">Sign Out</a>
                </div>
            <?php } else { ?>
                <a href="auth/login.php" class="btn btn-secondary" style="border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); color: #fff;">Sign In</a>
                <a href="auth/register.php" class="btn btn-primary">Join AgroNava</a>
            <?php } ?>
        </div>
    </header>

    <!-- Master Hero Splash -->
    <main class="hero-section" id="hero">
        <div class="hero-layout">
            <div class="hero-content-main animate-slide">
                <div class="badge-premium">
                    <span>🌱</span> Empowering Indian Agriculture
                </div>
                <h1 class="hero-title-main">
                    <span class="gradient-text-1">Direct Sales.</span>
                    <span class="gradient-text-2">Zero Middlemen Commission.</span>
                </h1>
                <p class="hero-desc-main">
                    Welcome to the heart of direct agricultural trading. AgroNava bridges hard-working Indian growers directly with wholesale and commercial buyers. Enjoy absolute transparency, live Mandi pricing metrics, and secure, encrypted QR dispatch settlements.
                </p>
                
                <!-- Dual Gateway Portal Cards -->
                <div class="gateway-card-container">
                    <!-- Farmer Gate -->
                    <a href="<?php echo $is_logged_in ? 'farmer/dashboard.php' : 'auth/login_farmer.php'; ?>" class="gateway-card gateway-card-grower">
                        <div class="gateway-card-header">
                            <div class="gateway-card-icon">👨‍🌾</div>
                            <span class="gateway-card-badge">Soil to Market</span>
                        </div>
                        <div class="gateway-card-content">
                            <h3 class="gateway-card-title">Grower Console</h3>
                            <p class="gateway-card-desc">List crops at premium prices, track local government MSPs, and capture real revenue.</p>
                        </div>
                        <div class="gateway-card-footer">
                            Enter Farmer Portal ➔
                        </div>
                    </a>
                    
                    <!-- Buyer Gate -->
                    <a href="<?php echo $is_logged_in ? 'buyer/dashboard.php' : 'auth/login_buyer.php'; ?>" class="gateway-card gateway-card-buyer">
                        <div class="gateway-card-header">
                            <div class="gateway-card-icon">🛒</div>
                            <span class="gateway-card-badge">Direct Procurement</span>
                        </div>
                        <div class="gateway-card-content">
                            <h3 class="gateway-card-title">Buyer Terminal</h3>
                            <p class="gateway-card-desc">Procure top-tier verified fresh crops directly from farms with secure digital escrow settlements.</p>
                        </div>
                        <div class="gateway-card-footer">
                            Enter Buyer Terminal ➔
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Graphic HUD Visual Overlay -->
            <div class="hero-hud-wrapper animate-fade">
                <div class="hud-circle-ring">
                    <div class="hud-inner-glow"></div>
                </div>
                
                <!-- Farmer Preview Element -->
                <div class="hud-floating-badge grower-badge-floating">
                    <span>🌾</span> Basmati: ₹6,500/Qtl
                </div>
                
                <!-- Cyber Dashboard Window mockup -->
                <div class="floating-dashboard-preview">
                    <div class="hud-header-bar">
                        <div class="hud-avatar">
                            <div class="hud-avatar-circle">🌾</div>
                            <div>
                                <div class="hud-title">AgroNava Live Trade</div>
                                <div class="hud-subtitle">Secure Escrow Settle</div>
                            </div>
                        </div>
                        <span class="hud-badge">Direct Sourced</span>
                    </div>
                    
                    <div class="hud-row">
                        <div class="hud-row-left">
                            <span class="hud-row-icon">👨‍🌾</span>
                            <span>Grower Rajesh Kumar</span>
                        </div>
                        <span class="hud-row-val">Punjab</span>
                    </div>
                    
                    <div class="hud-row">
                        <div class="hud-row-left">
                            <span class="hud-row-icon">📦</span>
                            <span>Basmati Paddy Rice</span>
                        </div>
                        <span class="hud-row-val" style="color: #10b981;">450 kg Left</span>
                    </div>
                    
                    <div class="hud-row" style="background: rgba(6, 182, 212, 0.05); border-color: rgba(6, 182, 212, 0.2);">
                        <div class="hud-row-left">
                            <span class="hud-row-icon" style="color: #06b6d4;">🛡️</span>
                            <span>Distributor Rating</span>
                        </div>
                        <span class="hud-row-val" style="color: #06b6d4;">98 Score</span>
                    </div>
                    
                    <div style="text-align: center; margin-top: 14px; font-size: 11px; color: #64748b;">
                        🔒 Encrypted Handshake Verification Active
                    </div>
                </div>
                
                <!-- Buyer Preview Element -->
                <div class="hud-floating-badge buyer-badge-floating">
                    <span>🚜</span> 100% Middleman-Free
                </div>
            </div>
        </div>
    </main>

    <!-- Mandi Live Financial Ticker Section -->
    <section class="mandi-section" id="mandi-ticker">
        <div class="mandi-title-area">
            <h2>Live Crop Mandi Pricing Index</h2>
            <p>Real-time transaction rates tracked across major local markets. Compare the transparency of direct trading.</p>
        </div>
        
        <!-- Live scrolling ticker marquee -->
        <div class="marquee-wrapper">
            <div class="marquee-content">
                <div class="marquee-item">🌾 Wheat (Grade-A): <span style="color: #10b981;">₹2,350/Qtl</span> <span class="ticker-badge">▲ +4.2%</span></div>
                <div class="marquee-item">🍚 Basmati Paddy: <span style="color: #10b981;">₹6,510/Qtl</span> <span class="ticker-badge">▲ +12.8%</span></div>
                <div class="marquee-item">🥔 Potatoes (Kufri): <span style="color: #ef4444;">₹1,380/Qtl</span> <span class="ticker-badge-down">▼ -1.5%</span></div>
                <div class="marquee-item">🧅 Red Onions: <span style="color: #10b981;">₹1,950/Qtl</span> <span class="ticker-badge">▲ +3.4%</span></div>
                <div class="marquee-item">🌱 Sarson Seeds: <span style="color: #10b981;">₹5,400/Qtl</span> <span class="ticker-badge">▲ +6.7%</span></div>
                
                <!-- Duplicate for seamless scroll -->
                <div class="marquee-item">🌾 Wheat (Grade-A): <span style="color: #10b981;">₹2,350/Qtl</span> <span class="ticker-badge">▲ +4.2%</span></div>
                <div class="marquee-item">🍚 Basmati Paddy: <span style="color: #10b981;">₹6,510/Qtl</span> <span class="ticker-badge">▲ +12.8%</span></div>
                <div class="marquee-item">🥔 Potatoes (Kufri): <span style="color: #ef4444;">₹1,380/Qtl</span> <span class="ticker-badge-down">▼ -1.5%</span></div>
                <div class="marquee-item">🧅 Red Onions: <span style="color: #10b981;">₹1,950/Qtl</span> <span class="ticker-badge">▲ +3.4%</span></div>
                <div class="marquee-item">🌱 Sarson Seeds: <span style="color: #10b981;">₹5,400/Qtl</span> <span class="ticker-badge">▲ +6.7%</span></div>
            </div>
        </div>
        
        <!-- Dynamic DB Crops Display Grid -->
        <h3 style="font-size: 24px; color: white; margin-bottom: 24px; text-align: left; font-family: 'Outfit', sans-serif;">
            ⚡ Active Listings in Marketplace
        </h3>
        
        <div class="crops-grid-landing">
            <?php foreach ($db_crops as $crop) { ?>
                <div class="crop-card-landing">
                    <span class="crop-card-badge-origin">₹<?php echo htmlspecialchars($crop['price']); ?>/Kg</span>
                    <span style="font-size: 32px; display: block; margin-bottom: 12px;">🌾</span>
                    <h4 class="crop-card-name"><?php echo htmlspecialchars($crop['crop_name']); ?></h4>
                    
                    <div class="crop-card-farmer">
                        <span>👨‍🌾</span> <?php echo htmlspecialchars($crop['farmer_name']); ?>
                        <div style="font-size: 10px; color: #10b981; font-weight: 700; margin-top: 2px;">
                            <?php echo htmlspecialchars($crop['distributor_badge']); ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                        <?php 
                        $badges = isset($crop['sustainability_badges']) ? explode(',', $crop['sustainability_badges']) : [];
                        foreach ($badges as $badge) { 
                            if(trim($badge) != '') {
                            ?>
                                <span style="font-size: 10px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 4px; color: #94a3b8;">
                                    🍀 <?php echo htmlspecialchars(trim($badge)); ?>
                                </span>
                            <?php 
                            }
                        } ?>
                    </div>
                    
                    <div class="crop-card-price-row">
                        <div>
                            <div class="crop-card-price-label">DIRECT VALUE PRICE</div>
                            <div class="crop-card-price">₹<?php echo htmlspecialchars($crop['price'] * 100); ?><span style="font-size: 13px; color: #64748b; font-weight: 500;">/Qtl</span></div>
                        </div>
                        <div class="crop-card-volume">
                            <div>Stock: <span><?php echo htmlspecialchars($crop['quantity']); ?> Kg</span></div>
                            <div style="font-size: 10px; color: #10b981; font-weight: 700; margin-top: 4px;">★ <?php echo number_format($crop['rating_avg'] ?? 4.5, 1); ?> (<?php echo $crop['review_count'] ?? 5; ?> reviews)</div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="market_prices.php" class="btn btn-secondary" style="border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); color: #fff;">
                📊 View Live MSP price index comparisons
            </a>
        </div>
    </section>

    <!-- The Dual Console Separated Spotlight -->
    <section class="separation-section" id="dual-showcase">
        <div class="separation-layout">
            <div class="section-header-separated">
                <h2>Two Distinct Terminals. One Unified Engine.</h2>
                <p>AgroNava operates on a strictly separated ecosystem. The main landing page coordinates public metrics, while highly engineered, secure role-specific sub-dashboards operate independently behind safe authentication portals.</p>
            </div>
            
            <div class="separation-grid">
                <!-- Farmer Dashboard Console Details -->
                <div class="console-preview-card farmer-console-card">
                    <div>
                        <span class="console-label-indicator">Farmer Sub-Page Preview</span>
                        <h3 class="console-title">Grower Dashboard Panel</h3>
                        <p class="console-desc">
                            Designed inside a rich Sunset Amber matrix. The Farmer Console provides tools for direct, optimized field-to-market trading:
                        </p>
                        
                        <ul class="console-features-list">
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Crop listings inventory:</strong> Register new crops, upload photos, check stock levels instantly.</div>
                            </li>
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Live Government MSP comparison:</strong> Set prices intelligently aligned with nationwide commodity rates.</div>
                            </li>
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Secure QR code dispatch:</strong> Auto-generate shipping QR codes to verify cargo delivery physically.</div>
                            </li>
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Distributor Score metrics:</strong> Certified badges and performance rankings shown to buyers.</div>
                            </li>
                        </ul>
                    </div>
                    
                    <div>
                        <a href="<?php echo $is_logged_in ? 'farmer/dashboard.php' : 'auth/login_farmer.php'; ?>" class="console-btn farmer-console-btn">
                            Enter Farmer Console ➔
                        </a>
                    </div>
                </div>
                
                <!-- Buyer Terminal Console Details -->
                <div class="console-preview-card buyer-console-card">
                    <div>
                        <span class="console-label-indicator">Buyer Sub-Page Preview</span>
                        <h3 class="console-title">Buyer Terminal Marketplace</h3>
                        <p class="console-desc">
                            Designed inside a high-tech Cyber Cyan shell. The Buyer Marketplace offers industrial-strength procurement controls:
                        </p>
                        
                        <ul class="console-features-list">
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Interactive crop filtering:</strong> Sort items by sustainability score, price tags, or geographic regions.</div>
                            </li>
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Digital Escrow Settle:</strong> Deposit funds safely; release money instantly upon physical crop confirmation.</div>
                            </li>
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Automated PDF invoicing:</strong> One-click professional invoice generation for company logs.</div>
                            </li>
                            <li>
                                <span class="list-bullet">✓</span>
                                <div><strong>Direct grower encrypted chat:</strong> Communicate in real-time about quality standards and logistics.</div>
                            </li>
                        </ul>
                    </div>
                    
                    <div>
                        <a href="<?php echo $is_logged_in ? 'buyer/dashboard.php' : 'auth/login_buyer.php'; ?>" class="console-btn buyer-console-btn">
                            Enter Buyer Terminal ➔
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Crop Journey Flowchart (Timeline) -->
    <section class="timeline-section" id="workflow">
        <div class="mandi-title-area">
            <h2>The Middleman-Free Crop Pipeline</h2>
            <p>From seed in Punjab to cargo dispatch in Delhi, our cryptographic escrow ensures trust at every intersection.</p>
        </div>
        
        <div class="timeline-grid">
            <div class="timeline-step step-1">
                <div class="timeline-bubble">🌾</div>
                <h4 class="timeline-step-title">1. List Crop & Price</h4>
                <p class="timeline-step-desc">Farmers list active crops directly. Government MSP benchmarks guide honest pricing structures.</p>
            </div>
            
            <div class="timeline-step step-2">
                <div class="timeline-bubble">🤝</div>
                <h4 class="timeline-step-title">2. Buyer Procurement</h4>
                <p class="timeline-step-desc">Buyers browse the marketplace and execute orders directly. Zero broker fees incurred.</p>
            </div>
            
            <div class="timeline-step step-3">
                <div class="timeline-bubble">🔒</div>
                <h4 class="timeline-step-title">3. Escrow Securing</h4>
                <p class="timeline-step-desc">Funds are stored safely in a digital holding locker, protecting both participating nodes.</p>
            </div>
            
            <div class="timeline-step step-4">
                <div class="timeline-bubble">📱</div>
                <h4 class="timeline-step-title">4. Scan QR Deliver</h4>
                <p class="timeline-step-desc">Buyer scans the grower's dispatch QR code upon arrival. Funds release instantly.</p>
            </div>
        </div>
    </section>

    <!-- Performance Stats Banner -->
    <section class="stats-banner">
        <div class="stats-banner-container">
            <div>
                <div class="stat-item-number">₹0</div>
                <div class="stat-item-label">Broker Commission</div>
                <div class="stat-item-sub">Fixed 0% rate on all trades</div>
            </div>
            <div>
                <div class="stat-item-number">10k+</div>
                <div class="stat-item-label">Verified Farmers</div>
                <div class="stat-item-sub">Registered across 12 states</div>
            </div>
            <div>
                <div class="stat-item-number">Instant</div>
                <div class="stat-item-label">QR Settlement</div>
                <div class="stat-item-sub">Immediate banker dispatch transfer</div>
            </div>
            <div>
                <div class="stat-item-number">24/7</div>
                <div class="stat-item-label">Mandi Price Feed</div>
                <div class="stat-item-sub">Self-updating analytics database</div>
            </div>
        </div>
    </section>

    <!-- User Testimonials Section -->
    <section class="testimonials-section">
        <div class="mandi-title-area">
            <h2>Success Stories from the Soil</h2>
            <p>Read about the structural impact AgroNava has generated for direct Indian agricultural trade.</p>
        </div>
        
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <p class="testimonial-quote">
                    "Selling wheat used to mean traveling 40km to the local mandi and paying 8% commission to dalals. With AgroNava, I listed Sharbati Kanak right from my farm. An commercial buyer bought the entire lot within 2 days. The QR payout hit my account instantly!"
                </p>
                <div class="testimonial-user">
                    <div class="testimonial-avatar">🌾</div>
                    <div>
                        <div class="testimonial-username">Sardar Gurpreet Singh</div>
                        <div class="testimonial-userrole">Wheat Grower • Patiala, Punjab</div>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card">
                <p class="testimonial-quote">
                    "As a wholesale distributor in Gurgaon, securing high-quality potatoes consistently was always a logistical headache. The direct contact with certified growers and distributor score metric gives us 100% confidence before purchasing. Superb, clean interface."
                </p>
                <div class="testimonial-user">
                    <div class="testimonial-avatar">🧅</div>
                    <div>
                        <div class="testimonial-username">Aniket Sharma</div>
                        <div class="testimonial-userrole">Wholesale Buyer • Gurgaon, Haryana</div>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card">
                <p class="testimonial-quote">
                    "The Live MSP Ticker is a lifesaver. Farmers in my village now know the exact value of Basmati and reject low-ball agent pricing. AgroNava is truly the digital heart of farmer independence."
                </p>
                <div class="testimonial-user">
                    <div class="testimonial-avatar">👨‍🌾</div>
                    <div>
                        <div class="testimonial-username">Rajesh Kumar</div>
                        <div class="testimonial-userrole">Paddy & Potato Farmer • Haryana</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Master Footer -->
    <footer class="footer-landing">
        <div class="footer-grid">
            <div class="footer-logo-area">
                <h3>🌾 AgroNava</h3>
                <p>Bridging the soil of India directly with transparent, modern trading systems. Safe, rapid, direct.</p>
                <div style="font-size: 13px; color: #475569;">
                    © 2026 AgroNava Inc. All Rights Reserved.
                </div>
            </div>
            
            <div class="footer-column">
                <h4>Grower Links</h4>
                <ul>
                    <li><a href="auth/login_farmer.php">Farmer Login Portal</a></li>
                    <li><a href="auth/register.php?role=farmer">Register as Grower</a></li>
                    <li><a href="market_prices.php">Live Mandi Prices</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h4>Buyer Links</h4>
                <ul>
                    <li><a href="auth/login_buyer.php">Buyer Marketplace Terminal</a></li>
                    <li><a href="auth/register.php?role=buyer">Register commercial account</a></li>
                    <li><a href="market_prices.php">MSP Price delta</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h4>Security & Platform</h4>
                <ul>
                    <li><a href="auth/qr_login.php">Interactive Captcha & QR Access</a></li>
                    <li><a href="chat.php">Encrypted chat services</a></li>
                    <li><a href="update_active_db.php">System auto-migrator logs</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div>
                Crafted with peak visual excellence for direct agricultural progress.
            </div>
            <div style="display: flex; gap: 20px;">
                <a href="#hero">Back to Top ↑</a>
            </div>
        </div>
    </footer>

    <!-- Interactive Scroll Effect JavaScript -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const navbar = document.getElementById("navbar");
            window.addEventListener("scroll", () => {
                if (window.scrollY > 50) {
                    navbar.classList.add("scrolled");
                } else {
                    navbar.classList.remove("scrolled");
                }
            });
        });
    </script>
</body>
</html>