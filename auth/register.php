<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../config/db.php");

// Initialize captcha text if not set
if (!isset($_SESSION['captcha_text'])) {
    $_SESSION['captcha_text'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
}

// Generate new captcha text on reload request
if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha_text'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
    echo $_SESSION['captcha_text'];
    exit();
}

$message = "";
$success_msg = "";

if (isset($_POST['register'])) {
    // Basic fields
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = $_POST['confirm_password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']); // derived from reg_type
    
    // Check captcha
    $captcha_input = strtoupper(trim($_POST['captcha']));
    if ($captcha_input !== $_SESSION['captcha_text']) {
        $message = "⚠️ Captcha code is incorrect! Please try again.";
    } 
    // Check password confirmation
    else if ($password !== $confirm_password) {
        $message = "⚠️ Passwords do not match!";
    } 
    // Check bank account confirmation
    else if ($_POST['bank_account_no'] !== $_POST['confirm_account_no']) {
        $message = "⚠️ Bank Account Numbers do not match!";
    }
    else {
        // Check if email already exists
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $message = "⚠️ This Email ID is already registered!";
        } else {
            // Retrieve other AgroNava fields
            $reg_type = mysqli_real_escape_string($conn, $_POST['role']);
            $reg_level = mysqli_real_escape_string($conn, $_POST['reg_level']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $gender = mysqli_real_escape_string($conn, $_POST['gender']);
            $dob = mysqli_real_escape_string($conn, $_POST['dob']);
            $relation_type = mysqli_real_escape_string($conn, $_POST['relation_type']);
            $relation_name = mysqli_real_escape_string($conn, $_POST['relation_name']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $pincode = mysqli_real_escape_string($conn, $_POST['pincode']);
            $state = mysqli_real_escape_string($conn, $_POST['state']);
            $district = mysqli_real_escape_string($conn, $_POST['district']);
            $tehsil = mysqli_real_escape_string($conn, $_POST['tehsil']);
            $city_village = mysqli_real_escape_string($conn, $_POST['city_village']);
            $post = mysqli_real_escape_string($conn, $_POST['post']);
            
            $photo_id_type = mysqli_real_escape_string($conn, $_POST['photo_id_type']);
            $photo_id_number = mysqli_real_escape_string($conn, $_POST['photo_id_number']);
            $mobile_no = mysqli_real_escape_string($conn, $_POST['mobile_no']);
            $license_no = mysqli_real_escape_string($conn, $_POST['license_no']);
            
            $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);
            $bank_holder_name = mysqli_real_escape_string($conn, $_POST['bank_holder_name']);
            $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
            $bank_account_no = mysqli_real_escape_string($conn, $_POST['bank_account_no']);
            $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
            $branch_address = mysqli_real_escape_string($conn, $_POST['branch_address']);
            
            $get_sms = isset($_POST['get_sms']) ? 1 : 0;
            $get_email = isset($_POST['get_email']) ? 1 : 0;
            
            // Construct name for backwards compatibility
            $full_name = trim($first_name . " " . $middle_name . " " . $last_name);
            if (empty($full_name)) {
                $full_name = "AgroNava Member";
            }
            
            // Handle File Uploads
            $passbook_filename = "";
            $id_proof_filename = "";
            
            // Ensure uploads directory exists
            if (!is_dir('../uploads/users/')) {
                mkdir('../uploads/users/', 0777, true);
            }
            
            // Upload Passbook
            if (isset($_FILES['passbook_image']) && $_FILES['passbook_image']['error'] == 0) {
                $file_tmp = $_FILES['passbook_image']['tmp_name'];
                $file_name = basename($_FILES['passbook_image']['name']);
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $passbook_filename = "passbook_" . time() . "_" . uniqid() . "." . $ext;
                $target_path = "../uploads/users/" . $passbook_filename;
                
                move_uploaded_file($file_tmp, $target_path);
            }
            
            // Upload ID Proof
            if (isset($_FILES['id_proof_image']) && $_FILES['id_proof_image']['error'] == 0) {
                $file_tmp = $_FILES['id_proof_image']['tmp_name'];
                $file_name = basename($_FILES['id_proof_image']['name']);
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $id_proof_filename = "id_" . time() . "_" . uniqid() . "." . $ext;
                $target_path = "../uploads/users/" . $id_proof_filename;
                
                move_uploaded_file($file_tmp, $target_path);
            }
            
            // Insert into DB
            $sql = "INSERT INTO users (
                name, email, password, role,
                reg_type, reg_level, title, first_name, middle_name, last_name,
                gender, dob, relation_type, relation_name, address, pincode,
                state, district, tehsil, city_village, post,
                photo_id_type, photo_id_number, mobile_no, license_no,
                ifsc_code, bank_holder_name, bank_name, bank_account_no, branch_name, branch_address,
                passbook_image, id_proof_image, get_sms, get_email
            ) VALUES (
                '$full_name', '$email', '$password', '$role',
                '$reg_type', '$reg_level', '$title', '$first_name', '$middle_name', '$last_name',
                '$gender', '$dob', '$relation_type', '$relation_name', '$address', '$pincode',
                '$state', '$district', '$tehsil', '$city_village', '$post',
                '$photo_id_type', '$photo_id_number', '$mobile_no', '$license_no',
                '$ifsc_code', '$bank_holder_name', '$bank_name', '$bank_account_no', '$branch_name', '$branch_address',
                '$passbook_filename', '$id_proof_filename', '$get_sms', '$get_email'
            )";
            
            if (mysqli_query($conn, $sql)) {
                $success_msg = "🎉 Registration successful! Your AgroNava membership details have been verified and saved.";
                // Clear captcha
                $_SESSION['captcha_text'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
            } else {
                $message = "⚠️ Database Error: " . mysqli_error($conn);
            }
        }
    }
}

// Preset role
$preset_role = isset($_GET['role']) ? $_GET['role'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Membership Registration | AgroNava</title>
    
    <!-- Design styling system & Google Fonts -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Elite Brand UI Styling */
        body.auth-custom-bg {
            display: block;
            padding: 40px 20px;
            background: #f7fef4;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* ── Light Version Background ── */
        .bg-light {
            position: fixed; inset: 0; z-index: -1;
            background: linear-gradient(160deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
        }
        .grid-bg-light {
            position: fixed; inset: 0; z-index: -1;
            background-image:
              linear-gradient(rgba(34,197,94,0.15) 1px, transparent 1px),
              linear-gradient(90deg, rgba(34,197,94,0.15) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 25s linear infinite;
        }
        @keyframes gridMove { from{background-position:0 0;} to{background-position:60px 60px;} }

        /* Light Orbs */
        .orb-light {
            position: fixed; border-radius: 50%;
            filter: blur(90px); z-index: -1;
            animation: orbPulse 15s ease-in-out infinite;
        }
        .orb-1-light { width:500px;height:500px;background:#4ade80;top:-150px;left:-150px;opacity:0.3; }
        .orb-2-light { width:350px;height:350px;background:#fcd34d;bottom:-100px;right:-100px;opacity:0.25;animation-delay:-6s; }
        .orb-3-light { width:250px;height:250px;background:#93c5fd;top:40%;left:55%;opacity:0.2;animation-delay:-3s; }
        @keyframes orbPulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.15);} }

        /* Light Particles */
        .particles-light { position:fixed;inset:0;z-index:0;pointer-events:none; }
        .p-light {
            position:absolute; border-radius:50%; background:#f59e0b; opacity:0;
            animation: pUp linear infinite;
        }
        @keyframes pUp { 0%{transform:translateY(110vh);opacity:0;} 10%{opacity:0.4;} 90%{opacity:0.1;} 100%{transform:translateY(-10vh);opacity:0;} }

        /* Light Sprouts */
        .sprouts-light { position:fixed;bottom:0;left:0;right:0;z-index:0;pointer-events:none; }
        .sprout-light { position:absolute;bottom:0;font-size:20px;animation:sproutSway ease-in-out infinite; }
        @keyframes sproutSway { 0%,100%{transform:rotate(-5deg);} 50%{transform:rotate(5deg);} }
        
        .register-elite-container {
            width: 100%;
            max-width: 1160px;
            background: rgba(255, 255, 255, 0.82); /* Premium light frosted glass */
            backdrop-filter: blur(25px) saturate(120%);
            -webkit-backdrop-filter: blur(25px) saturate(120%);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.06);
            border-radius: 28px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.25fr;
            transition: var(--transition);
            position: relative;
            z-index: 10;
        }
        
        /* Left Brand Showcase Panel */
        .brand-showcase-panel {
            padding: 50px;
            background: linear-gradient(145deg, rgba(6, 95, 70, 0.9), rgba(2, 44, 34, 0.98));
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        .brand-showcase-panel::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 70%;
            height: 70%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 60%);
            z-index: 1;
            pointer-events: none;
        }
        
        /* Creative Typography branding */
        .brand-title-creative {
            font-family: 'Cinzel Decorative', 'Outfit', serif;
            font-size: 3.2rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -2px;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }
        
        .letter-cap-green {
            background: linear-gradient(135deg, #34d399, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 4.2rem;
            display: inline-block;
            filter: drop-shadow(0 0 15px rgba(52, 211, 153, 0.5));
        }
        
        .letter-cap-gold {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 4.2rem;
            display: inline-block;
            filter: drop-shadow(0 0 15px rgba(251, 191, 36, 0.5));
        }
        
        .letter-regular {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            color: #f8fafc;
            font-size: 2.6rem;
            letter-spacing: -1.5px;
        }
        
        .brand-tagline-letters {
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 5px;
            color: #94a3b8;
            margin-bottom: 24px;
            display: flex;
            gap: 1px;
            position: relative;
            z-index: 2;
        }
        
        .brand-tagline-letters span {
            display: inline-block;
            transition: var(--transition);
            background: linear-gradient(90deg, #94a3b8, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .brand-tagline-letters span:hover {
            transform: translateY(-3px) scale(1.15);
            background: linear-gradient(90deg, #fbbf24, #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .brand-desc {
            color: #cbd5e1;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }
        
        .brand-premium-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.25);
            padding: 8px 16px;
            border-radius: 50px;
            color: #fbbf24;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 30px;
            align-self: flex-start;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.1);
            position: relative;
            z-index: 2;
        }
        
        .premium-bullet-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 35px;
            position: relative;
            z-index: 2;
        }
        
        .premium-bullet-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #e2e8f0;
            font-size: 14px;
        }
        
        .bullet-icon {
            background: rgba(52, 211, 153, 0.15);
            color: #34d399;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }
        
        .bullet-title {
            font-weight: 700;
            color: white;
            display: block;
            margin-bottom: 2px;
        }
        
        .illustration-card-container {
            margin-top: auto;
            position: relative;
            display: flex;
            justify-content: center;
            z-index: 2;
        }
        
        .illustration-card-img {
            width: 100%;
            max-width: 320px;
            height: auto;
            border-radius: var(--radius-md);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transform: rotate(-1.5deg);
            transition: var(--transition);
            animation: floatIllustration 4.5s ease-in-out infinite;
        }
        
        .illustration-card-img:hover {
            transform: rotate(0deg) scale(1.03);
            box-shadow: 0 20px 45px rgba(16, 185, 129, 0.2);
        }
        
        @keyframes floatIllustration {
            0% { transform: translateY(0) rotate(-1.5deg); }
            50% { transform: translateY(-8px) rotate(1deg); }
            100% { transform: translateY(0) rotate(-1.5deg); }
        }
        
        /* Right Interactive Wizard Panel */
        .interactive-wizard-panel {
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .wizard-heading-creative {
            margin-bottom: 30px;
        }
        
        .wizard-heading-creative h2 {
            font-family: 'Syne', sans-serif;
            color: #0f172a; /* Premium Dark Slate */
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 8px;
        }
        
        .wizard-heading-creative p {
            color: #475569;
            font-size: 14px;
        }
        
        /* Enhanced Progress Steps */
        .step-progress-wrapper {
            margin-bottom: 40px;
            background: rgba(15, 23, 42, 0.03);
            border: 1px solid rgba(15, 23, 42, 0.06);
            padding: 16px 24px;
            border-radius: var(--radius-md);
            position: relative;
        }
        
        .step-progress-indicator-bar {
            position: absolute;
            top: 36px;
            left: 45px;
            right: 45px;
            height: 3px;
            background: rgba(15, 23, 42, 0.08);
            z-index: 1;
        }
        
        .step-progress-indicator-active {
            position: absolute;
            top: 36px;
            left: 45px;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #fbbf24);
            z-index: 2;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .step-nodes-container {
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 3;
        }
        
        .progress-step-node {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            width: 60px;
        }
        
        .node-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f1f5f9;
            border: 2px solid rgba(15, 23, 42, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #64748b;
            font-size: 14px;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        }
        
        .progress-step-node.active .node-circle {
            border-color: #34d399;
            color: white;
            background: linear-gradient(135deg, #059669, #10b981);
            box-shadow: 0 0 15px rgba(52, 211, 153, 0.3);
        }
        
        .progress-step-node.completed .node-circle {
            border-color: #fbbf24;
            color: #0f172a;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }
        
        .node-label-custom {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
            color: #64748b;
            transition: var(--transition);
        }
        
        .progress-step-node.active .node-label-custom {
            color: #34d399;
        }
        
        .progress-step-node.completed .node-label-custom {
            color: #b45309;
        }
        
        /* Dark Glassmorphic Inputs */
        .register-body-custom {
            min-height: 380px;
            margin-bottom: 30px;
        }
        
        .form-section-title {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: #b45309; /* Golden Amber */
            margin-bottom: 24px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            display: flex;
            align-items: center;
            gap: 10px;
            grid-column: span 2;
            letter-spacing: 0.5px;
        }
        
        .section-cap {
            background: rgba(245, 158, 11, 0.1);
            color: #b45309;
            padding: 6px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group-custom {
            margin-bottom: 24px;
            text-align: left;
        }
        
        .form-label-custom {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-control-custom {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.7); /* clean light background */
            border: 1px solid rgba(15, 23, 42, 0.12); /* clean slate border */
            border-radius: 12px;
            outline: none;
            font-size: 15px;
            color: #0f172a;
            transition: var(--transition);
        }
        
        .form-control-custom:focus {
            border-color: #34d399;
            background: #ffffff;
            box-shadow: 0 0 15px rgba(52, 211, 153, 0.15);
        }
        
        select.form-control-custom {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 16px;
            color: #0f172a;
        }
        
        select.form-control-custom option {
            background-color: #ffffff;
            color: #0f172a;
            padding: 10px;
        }
        
        .form-control-custom::placeholder {
            color: #94a3b8;
        }
        
        /* Grid divisions */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 600px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            .form-section-title {
                grid-column: span 1;
            }
        }
        
        /* Dynamic Verification Highlights */
        .verification-card-box {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.15);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #047857;
            font-size: 13px;
            font-weight: 500;
        }
        
        /* Custom Upload Cards */
        .glass-upload-card {
            border: 2px dashed rgba(15, 23, 42, 0.12);
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .glass-upload-card:hover {
            border-color: #34d399;
            background: rgba(52, 211, 153, 0.05);
        }
        
        .upload-icon-custom {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.1));
        }
        
        .upload-filename-custom {
            margin-top: 10px;
            font-size: 12px;
            color: #047857;
            font-weight: 700;
        }
        
        /* Creative Captcha layout */
        .captcha-badge-custom {
            letter-spacing: 8px;
            font-size: 24px;
            font-weight: 800;
            font-family: 'Outfit', monospace;
            color: #b45309;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            padding: 14px 28px;
            border-radius: 10px;
            border: 1px solid rgba(245, 158, 11, 0.3);
            display: inline-block;
            text-decoration: line-through;
            text-shadow: 0 0 10px rgba(245, 158, 11, 0.2);
            user-select: none;
            box-shadow: inset 0 4px 15px rgba(245, 158, 11, 0.05);
        }
        
        /* Glowing Buttons styling */
        .btn-wizard {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 12px;
            cursor: pointer;
            border: none;
            transition: var(--transition);
        }
        
        .btn-wizard-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-wizard-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.5);
            background: linear-gradient(135deg, #34d399, #10b981);
        }
        
        .btn-wizard-secondary {
            background: rgba(15, 23, 42, 0.04);
            color: #475569;
            border: 1px solid rgba(15, 23, 42, 0.08);
        }
        
        .btn-wizard-secondary:hover {
            background: rgba(15, 23, 42, 0.08);
            color: #0f172a;
            transform: translateY(-2px);
        }
        
        .wizard-footer-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            padding-top: 30px;
        }
        
        /* Notifications */
        .elite-alert-error {
            background: rgba(239, 68, 68, 0.05);
            color: #b91c1c;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid rgba(239, 68, 68, 0.15);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.4s ease;
        }
        
        .elite-alert-success {
            background: rgba(16, 185, 129, 0.05);
            color: #065f46;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid rgba(16, 185, 129, 0.15);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.4s ease;
        }
        
        .required-star-custom {
            color: #ef4444;
            font-weight: bold;
        }
        
        /* Shaking Error Animation */
        @keyframes shakeField {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }
        
        .animate-shake-field {
            animation: shakeField 0.4s ease-in-out;
            border-color: #ef4444 !important;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.25) !important;
        }
        
        /* Responsive adaptations */
        @media (max-width: 1024px) {
            .register-elite-container {
                grid-template-columns: 1fr;
                max-width: 680px;
            }
            .brand-showcase-panel {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                padding: 40px;
            }
            .illustration-card-container {
                display: none;
            }
            .interactive-wizard-panel {
                padding: 40px;
            }
        }
        
        @media (max-width: 600px) {
            body.auth-custom-bg {
                padding: 10px;
            }
            .register-elite-container {
                border-radius: 18px;
            }
            .brand-showcase-panel {
                padding: 30px 20px;
            }
            .interactive-wizard-panel {
                padding: 30px 20px;
            }
            .step-progress-wrapper {
                padding: 12px 10px;
            }
            .node-label-custom {
                display: none;
            }
            .step-progress-indicator-bar {
                top: 20px;
                left: 30px;
                right: 30px;
            }
            .step-progress-indicator-active {
                top: 20px;
                left: 30px;
            }
            .brand-title-creative {
                font-size: 2.4rem;
            }
            .letter-cap-green, .letter-cap-gold {
                font-size: 3.2rem;
            }
            .letter-regular {
                font-size: 2.0rem;
            }
        }
    </style>
</head>
<body class="auth-custom-bg">

    <div class="bg-light"></div>
    <div class="grid-bg-light"></div>
    <div class="orb-light orb-1-light"></div>
    <div class="orb-light orb-2-light"></div>
    <div class="orb-light orb-3-light"></div>
    <div class="particles-light" id="particles"></div>
    <div class="sprouts-light" id="sprouts"></div>

    <div style="text-align: center; margin-bottom: 20px; position: relative; z-index: 10;">
        <!-- Logo centered on top for beautiful presentation -->
        <a href="../index.php" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
            <span style="font-size: 28px; filter: drop-shadow(0 0 10px rgba(52, 211, 153, 0.5));">🌾</span>
            <span style="font-family: 'Outfit', sans-serif; font-size: 24px; font-weight: 900; color: #14532d; letter-spacing: 1px;">AgroNava Portal</span>
        </a>
    </div>

    <div class="register-elite-container animate-slide">
        
        <!-- LEFT: Brand Showcase Panel -->
        <div class="brand-showcase-panel">
            <div>
                <!-- Brand Title with creative letters and text gradients -->
                <h1 class="brand-title-creative">
                    <span class="letter-cap-green">A</span><span class="letter-regular">gro</span><span class="letter-cap-gold">N</span><span class="letter-regular">ava</span>
                </h1>
                
                <!-- Custom word-by-word letters display tagline -->
                <div class="brand-tagline-letters">
                    <span>E</span><span>L</span><span>I</span><span>T</span><span>E</span>
                    &nbsp;&nbsp;
                    <span>D</span><span>I</span><span>R</span><span>E</span><span>C</span><span>T</span><span>-</span><span>T</span><span>R</span><span>A</span><span>D</span><span>E</span>
                </div>
                
                <span class="brand-premium-pill">🛡️ Verified Direct Trade Ledger</span>
                
                <p class="brand-desc">Join our premium digital harvest community. Benefit from zero commissions, full price transparency, and secure transactions directly with farmers and wholesale buyers.</p>
                
                <!-- Premium bullet points -->
                <ul class="premium-bullet-list">
                    <li class="premium-bullet-item">
                        <span class="bullet-icon">✓</span>
                        <div>
                            <span class="bullet-title">Zero Intermediary Fees</span>
                            <span style="color: #94a3b8; font-size: 12px;">All revenues go straight to the agricultural grower.</span>
                        </div>
                    </li>
                    <li class="premium-bullet-item">
                        <span class="bullet-icon">✓</span>
                        <div>
                            <span class="bullet-title">Real-Time MSP Tracker</span>
                            <span style="color: #94a3b8; font-size: 12px;">Trade backed by verified local Mandi price listings.</span>
                        </div>
                    </li>
                    <li class="premium-bullet-item">
                        <span class="bullet-icon">✓</span>
                        <div>
                            <span class="bullet-title">Verified Bank Ledgers</span>
                            <span style="color: #94a3b8; font-size: 12px;">Instant digital payments direct to verified accounts.</span>
                        </div>
                    </li>
                </ul>
            </div>
            
            <!-- Graphic Illustration displaying generated high-end agricultural card -->
            <div class="illustration-card-container">
                <img src="../assets/images/agro_register_card.png" alt="AgroNava Digital Marketplace" class="illustration-card-img">
            </div>
        </div>
        
        <!-- RIGHT: Interactive Wizard Form Panel -->
        <div class="interactive-wizard-panel">
            
            <div class="wizard-heading-creative">
                <h2>Join the Elite Digital Harvest</h2>
                <p>Register as a verified AgroNava member to list harvests or place retail orders.</p>
            </div>
            
            <!-- Multi-Step Progress Tracker Wrapper -->
            <div class="step-progress-wrapper">
                <div class="step-progress-indicator-bar"></div>
                <div class="step-progress-indicator-active" id="progressBar"></div>
                
                <div class="step-nodes-container">
                    <div class="progress-step-node active" id="pstep-1" onclick="jumpToStep(1)">
                        <div class="node-circle">1</div>
                        <span class="node-label-custom">Account</span>
                    </div>
                    <div class="progress-step-node" id="pstep-2" onclick="jumpToStep(2)">
                        <div class="node-circle">2</div>
                        <span class="node-label-custom">Personal</span>
                    </div>
                    <div class="progress-step-node" id="pstep-3" onclick="jumpToStep(3)">
                        <div class="node-circle">3</div>
                        <span class="node-label-custom">Credentials</span>
                    </div>
                    <div class="progress-step-node" id="pstep-4" onclick="jumpToStep(4)">
                        <div class="node-circle">4</div>
                        <span class="node-label-custom">Financial</span>
                    </div>
                </div>
            </div>
            
            <!-- Registration Form -->
            <form method="POST" enctype="multipart/form-data" id="wizardForm">
                
                <div class="register-body-custom">
                    
                    <?php if ($message != "") { ?>
                        <div class="elite-alert-error">
                            <span>⚠️</span> <?php echo $message; ?>
                        </div>
                    <?php } ?>

                    <?php if ($success_msg != "") { ?>
                        <div class="elite-alert-success">
                            <span>🎉</span> <?php echo $success_msg; ?>
                            <a href="login.php" style="margin-left: auto; background: #fbbf24; color: #0f172a; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase;">Sign In Now</a>
                        </div>
                    <?php } ?>
                    
                    <!-- STEP 1: ACCOUNT TYPE & SETUP -->
                    <div class="register-step active" id="step-1">
                        <div class="form-grid-2">
                            <div class="form-section-title">
                                <span class="section-cap">🗝️</span> Step 1: Account Type & Basic Credentials
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="role">Registration Type <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="role" name="role" required>
                                    <option value="">-Select-</option>
                                    <option value="farmer" <?php if($preset_role == 'farmer') echo 'selected'; ?>>👨‍🌾 Farmer (I want to sell produce)</option>
                                    <option value="buyer" <?php if($preset_role == 'buyer') echo 'selected'; ?>>🛒 Buyer / Trader (I want to purchase produce)</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="reg_level">Registration Level <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="reg_level" name="reg_level" required>
                                    <option value="">-Select-</option>
                                    <option value="State" selected>State Level Registration</option>
                                    <option value="Mandi">Mandi Board Level Registration</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom" style="grid-column: span 2;">
                                <label class="form-label-custom" for="email">Email Address <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="email" id="email" name="email" placeholder="e.g. name@domain.com" required>
                                <span style="font-size: 11px; color: #64748b; margin-top: 6px; display: block;">Make sure this address is active; we verify registrations and send purchase orders here.</span>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="password">Password <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="password" id="password" name="password" placeholder="••••••••" required>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="confirm_password">Confirm Password <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- STEP 2: PERSONAL DETAILS -->
                    <div class="register-step" id="step-2">
                        <div class="form-grid-2">
                            <div class="form-section-title" style="grid-column: span 2;">
                                <span class="section-cap">👤</span> Step 2: Personal Profile Details
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="title">Title <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="title" name="title">
                                    <option value="">Select Please</option>
                                    <option value="Mr.">Mr.</option>
                                    <option value="Ms.">Ms.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Dr.">Dr.</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="first_name">First Name <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="first_name" name="first_name" placeholder="First Name">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="middle_name">Middle Name</label>
                                <input class="form-control-custom" type="text" id="middle_name" name="middle_name" placeholder="Middle Name (Optional)">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="last_name">Last Name <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="last_name" name="last_name" placeholder="Last Name">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="gender">Gender <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="gender" name="gender">
                                    <option value="">-Select-</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Transgender">Transgender</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="dob">Date of Birth <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="date" id="dob" name="dob">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="relation_type">Relation Type <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="relation_type" name="relation_type">
                                    <option value="">-Select-</option>
                                    <option value="S/O">Son Of (S/O)</option>
                                    <option value="D/O">Daughter Of (D/O)</option>
                                    <option value="W/O">Wife Of (W/O)</option>
                                    <option value="C/O">Care Of (C/O)</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="relation_name">Relation Name <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="relation_name" name="relation_name" placeholder="Father/Husband/Care Name">
                            </div>
                            
                            <div class="form-section-title" style="grid-column: span 2; margin-top: 15px;">
                                <span class="section-cap">📍</span> Address & Location Details
                            </div>
                            
                            <div class="form-group-custom" style="grid-column: span 2;">
                                <label class="form-label-custom" for="address">Address (Street/Block) <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="address" name="address" placeholder="Flat No, Street Name, Block No">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="pincode">Pincode <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" maxlength="6" id="pincode" name="pincode" placeholder="e.g. 302001">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="state">State <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="state" name="state" onchange="populateDistricts()">
                                    <option value="">-Select State-</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="district">District <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="district" name="district" onchange="populateTehsils()">
                                    <option value="">-Select District-</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="tehsil">Tehsil / Sub-district <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="tehsil" name="tehsil" onchange="populateVillages()">
                                    <option value="">-Select Tehsil-</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="city_village">City / Village <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="city_village" name="city_village">
                                    <option value="">-Select Village-</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="post">Post Office <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="post" name="post" placeholder="Local Post Office">
                            </div>
                        </div>
                    </div>
                    
                    <!-- STEP 3: PHOTO ID & LICENSING -->
                    <div class="register-step" id="step-3">
                        <div class="form-grid-2">
                            <div class="form-section-title">
                                <span class="section-cap">🆔</span> Step 3: Identity & Licensing Credentials
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="photo_id_type">Photo ID Type <span class="required-star-custom">*</span></label>
                                <select class="form-control-custom" id="photo_id_type" name="photo_id_type">
                                    <option value="">-Select-</option>
                                    <option value="Aadhaar Card">Aadhaar Card (UIDAI)</option>
                                    <option value="PAN Card">PAN Card (Income Tax Dept)</option>
                                    <option value="Voter ID Card">Voter ID Card (ECI)</option>
                                    <option value="Driving License">Driving License</option>
                                    <option value="Passport">Indian Passport</option>
                                </select>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="photo_id_number">Photo ID Number <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="photo_id_number" name="photo_id_number" placeholder="Enter Photo ID Number">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="mobile_no">Mobile Number <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="tel" maxlength="10" id="mobile_no" name="mobile_no" placeholder="10-Digit Mobile Number">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="license_no">Trading / Mandi License Number <span class="required-star-custom" id="licenseStar">*</span></label>
                                <input class="form-control-custom" type="text" id="license_no" name="license_no" placeholder="Enter License Number (e.g. L-2026-FARM)">
                                <span style="font-size: 11px; color: #64748b; margin-top: 6px; display: block;" id="licenseNote">Required for Buyers/Traders to verify trading status.</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- STEP 4: BANK DETAILS & ATTACHMENTS -->
                    <div class="register-step" id="step-4">
                        <div class="form-grid-2">
                            <div class="form-section-title" style="grid-column: span 2;">
                                <span class="section-cap">🏦</span> Step 4: Bank Account & Financial Verification
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="ifsc_code">Bank IFSC Code <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" maxlength="11" id="ifsc_code" name="ifsc_code" placeholder="e.g. SBIN0001234" onchange="autoFillBankDetails()">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="bank_holder_name">Account Holder Name (as per Bank A/C) <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="bank_holder_name" name="bank_holder_name" placeholder="Holder Name">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="bank_name">Bank Name <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="bank_name" name="bank_name" placeholder="State Bank of India / HDFC Bank">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="branch_name">Branch Name <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="branch_name" name="branch_name" placeholder="Branch Name">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="bank_account_no">Bank Account Number <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="password" id="bank_account_no" name="bank_account_no" placeholder="••••••••••••">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="confirm_account_no">Confirm Account Number <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="confirm_account_no" name="confirm_account_no" placeholder="Re-Enter Account Number">
                            </div>
                            
                            <div class="form-group-custom" style="grid-column: span 2;">
                                <label class="form-label-custom" for="branch_address">Branch Address <span class="required-star-custom">*</span></label>
                                <input class="form-control-custom" type="text" id="branch_address" name="branch_address" placeholder="Complete address of the Bank Branch">
                            </div>
                            
                            <div class="form-section-title" style="grid-column: span 2; margin-top: 15px;">
                                <span class="section-cap">📁</span> Official Documents Verification Scans
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom">Passbook Scan / Cancelled Check <span class="required-star-custom">*</span></label>
                                <div class="glass-upload-card" onclick="document.getElementById('passbook_image').click();">
                                    <span class="upload-icon-custom">📖</span>
                                    <strong style="color: white; font-size: 13px;">Upload Copy of Passbook</strong>
                                    <p style="font-size: 11px; color: #64748b; margin-top: 4px;">Click to browse JPG, PNG or PDF (Max 2MB)</p>
                                    <input type="file" id="passbook_image" name="passbook_image" accept="image/*,application/pdf" onchange="displayFilename(this, 'passbook_label')">
                                    <div class="upload-filename-custom" id="passbook_label"></div>
                                </div>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom">ID Proof Scan <span class="required-star-custom">*</span></label>
                                <div class="glass-upload-card" onclick="document.getElementById('id_proof_image').click();">
                                    <span class="upload-icon-custom">🪪</span>
                                    <strong style="color: white; font-size: 13px;">Upload Scan Copy Of ID Proof</strong>
                                    <p style="font-size: 11px; color: #64748b; margin-top: 4px;">Click to browse JPG, PNG or PDF (Max 2MB)</p>
                                    <input type="file" id="id_proof_image" name="id_proof_image" accept="image/*,application/pdf" onchange="displayFilename(this, 'id_label')">
                                    <div class="upload-filename-custom" id="id_label"></div>
                                </div>
                            </div>
                            
                            <div class="form-section-title" style="grid-column: span 2; margin-top: 15px;">
                                <span class="section-cap">⚙️</span> Human Verification & Acknowledgement
                            </div>
                            
                            <div class="form-group-custom" style="grid-column: span 2;">
                                <label class="form-label-custom">Registration Acknowledgement Notifications</label>
                                <div style="display: flex; gap: 30px; margin-top: 8px;">
                                    <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; font-size: 14px; color: white;">
                                        <input type="checkbox" name="get_sms" checked style="width: 18px; height: 18px; accent-color: #10b981;">
                                        Get SMS Alerts
                                    </label>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; font-size: 14px; color: white;">
                                        <input type="checkbox" name="get_email" checked style="width: 18px; height: 18px; accent-color: #10b981;">
                                        Get Email Alerts
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Dynamic Captcha Box -->
                            <div class="form-group-custom" style="grid-column: span 2; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 20px; border-radius: 14px;">
                                <label class="form-label-custom" for="captcha">Captcha letters are case sensitive <span class="required-star-custom">*</span></label>
                                
                                <div style="display: flex; align-items: center; gap: 15px; margin-top: 8px; flex-wrap: wrap;">
                                    <span class="captcha-badge-custom" id="captchaBox"><?php echo $_SESSION['captcha_text']; ?></span>
                                    <button type="button" class="btn-refresh" onclick="refreshCaptcha()" title="Refresh Captcha" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; transition: var(--transition); color: white;">🔄</button>
                                    
                                    <input class="form-control-custom" type="text" id="captcha" name="captcha" placeholder="Enter Captcha Code" required style="max-width: 220px; text-transform: uppercase;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="wizard-footer-custom">
                    <button type="button" class="btn-wizard btn-wizard-secondary" id="prevBtn" onclick="nextPrev(-1)" style="display: none;">⬅️ Back Step</button>
                    
                    <p id="signinLink" style="font-size: 14px; color: #94a3b8; font-weight: 500; display: flex; align-items: center;">
                        Already registered? <a href="login.php" style="color: #fbbf24; font-weight: 700; margin-left: 5px; transition: var(--transition);">Sign In here</a>
                    </p>
                    
                    <button type="button" class="btn-wizard btn-wizard-primary" id="nextBtn" onclick="nextPrev(1)" style="margin-left: auto;">Next Step ➡️</button>
                </div>
                
            </form>
        </div>
    </div>

    <!-- Scripting for Multi-Step and Dynamics -->
    <script>
        // State-District-Tehsil-Village Local Data Directory
        const stateData = {
            "Rajasthan": {
                "Jaipur": {
                    "Amber": ["Amber Village", "Kookas Mandi", "Achrol Village", "Chandwaji"],
                    "Sanganer": ["Sanganer Town", "Bhankrota", "Muhana Mandi Area", "Watika"],
                    "Phulera": ["Sambhar Lake", "Phulera Town", "Jobner Village", "Narena"]
                },
                "Jodhpur": {
                    "Luni": ["Luni Village", "Kudi Mandi Area", "Salawas Handcrafts", "Boranada"],
                    "Shergarh": ["Shergarh Town", "Solankiyatala", "Sai Village", "Tena"]
                },
                "Udaipur": {
                    "Girwa": ["Girwa Town", "Bari Lake Area", "Kavita Village", "Sukher"],
                    "Mavli": ["Mavli Town", "Lopada Village", "Vasni Mandi Area", "Fatehnagar"]
                }
            },
            "Tamil Nadu": {
                "Chennai": {
                    "Egmore": ["Egmore Mandi Area", "Nungambakkam Market", "Chetpet Hub"],
                    "Mylapore": ["Mylapore Market Ward 1", "Mylapore Ward 2", "Royapettah"]
                },
                "Coimbatore": {
                    "Pollachi": ["Pollachi North Basins", "Pollachi Coconut Market", "Zamin Uthukuli"],
                    "Mettupalayam": ["Mettupalayam Potato Mandi", "Karamadai Hub", "Sirumugai Base"]
                }
            },
            "Haryana": {
                "Gurugram": {
                    "Gurugram": ["Gurugram Crop Mandi", "Sohna Grain Base", "Bhondsi Farms", "Badshahpur"],
                    "Pataudi": ["Pataudi Grain Mandi", "Farrukhnagar Salt base", "Hailey Mandi Depot"]
                },
                "Faridabad": {
                    "Ballabgarh": ["Ballabgarh Grain Market", "Chawla Farms", "Tigaon Village"],
                    "Faridabad": ["Faridabad Sector 1 Village", "Faridabad Sector 2 Base"]
                }
            },
            "Punjab": {
                "Amritsar": {
                    "Amritsar-I": ["Amritsar Main Grain Mandi", "Amritsar Ward 2 Market"],
                    "Ajnala": ["Ajnala Wheat Hub", "Ramdas Market", "Chogawan Paddy Fields"]
                },
                "Ludhiana": {
                    "Ludhiana West": ["Ludhiana Ward A Market", "Ludhiana Grain Stockyard"],
                    "Samrala": ["Samrala Wheat Mandi", "Machhiwara Rice Market", "Khamano Farms"]
                }
            }
        };

        // Initialize States dropdown on load
        window.addEventListener('DOMContentLoaded', () => {
            const stateSel = document.getElementById('state');
            for (let state in stateData) {
                stateSel.options[stateSel.options.length] = new Option(state, state);
            }
            
            // Adjust License validation rules based on role
            const roleSel = document.getElementById('role');
            roleSel.addEventListener('change', () => {
                const licStar = document.getElementById('licenseStar');
                const licInput = document.getElementById('license_no');
                if (roleSel.value === 'buyer') {
                    licStar.style.display = 'inline';
                    licInput.required = true;
                    licInput.placeholder = "Enter Buyer Trading License * (Required)";
                } else {
                    licStar.style.display = 'none';
                    licInput.required = false;
                    licInput.placeholder = "Enter License Number (Optional for Farmers)";
                }
            });
            
            // Initialize progress steps
            showStep(currentStep);
        });

        function populateDistricts() {
            const stateSel = document.getElementById('state');
            const distSel = document.getElementById('district');
            const tehSel = document.getElementById('tehsil');
            const villSel = document.getElementById('city_village');
            
            distSel.length = 1; // reset
            tehSel.length = 1;
            villSel.length = 1;
            
            const selectedState = stateSel.value;
            if (selectedState && stateData[selectedState]) {
                for (let district in stateData[selectedState]) {
                    distSel.options[distSel.options.length] = new Option(district, district);
                }
            }
        }

        function populateTehsils() {
            const stateSel = document.getElementById('state');
            const distSel = document.getElementById('district');
            const tehSel = document.getElementById('tehsil');
            const villSel = document.getElementById('city_village');
            
            tehSel.length = 1; // reset
            villSel.length = 1;
            
            const selectedState = stateSel.value;
            const selectedDist = distSel.value;
            if (selectedState && selectedDist && stateData[selectedState][selectedDist]) {
                for (let tehsil in stateData[selectedState][selectedDist]) {
                    tehSel.options[tehSel.options.length] = new Option(tehsil, tehsil);
                }
            }
        }

        function populateVillages() {
            const stateSel = document.getElementById('state');
            const distSel = document.getElementById('district');
            const tehSel = document.getElementById('tehsil');
            const villSel = document.getElementById('city_village');
            
            villSel.length = 1; // reset
            
            const selectedState = stateSel.value;
            const selectedDist = distSel.value;
            const selectedTeh = tehSel.value;
            if (selectedState && selectedDist && selectedTeh && stateData[selectedState][selectedDist][selectedTeh]) {
                const villages = stateData[selectedState][selectedDist][selectedTeh];
                for (let i = 0; i < villages.length; i++) {
                    villSel.options[villSel.options.length] = new Option(villages[i], villages[i]);
                }
            }
        }

        // Auto fill bank details on entering mock IFSC for amazing UI experience
        function autoFillBankDetails() {
            const ifsc = document.getElementById('ifsc_code').value.toUpperCase();
            if (ifsc.length >= 4) {
                const bankNameInput = document.getElementById('bank_name');
                const branchNameInput = document.getElementById('branch_name');
                const branchAddrInput = document.getElementById('branch_address');
                
                if (ifsc.startsWith('SBIN')) {
                    bankNameInput.value = "State Bank of India";
                    branchNameInput.value = "Central Mandi Branch";
                    branchAddrInput.value = "Plot 10, Mandi Yard Road, Agro Hub District";
                } else if (ifsc.startsWith('HDFC')) {
                    bankNameInput.value = "HDFC Bank Ltd";
                    branchNameInput.value = "Agriculture Finance Wing";
                    branchAddrInput.value = "M.G. Road branch, Town Centre Area";
                } else if (ifsc.startsWith('ICIC')) {
                    bankNameInput.value = "ICICI Bank";
                    branchNameInput.value = "Rural Development Branch";
                    branchAddrInput.value = "Agrarian Square, Mandi Link Circle";
                } else if (ifsc.startsWith('PUNB')) {
                    bankNameInput.value = "Punjab National Bank";
                    branchNameInput.value = "Farmer's Trust Building Branch";
                    branchAddrInput.value = "G.T. Road bypass, Mandi Division";
                }
            }
        }

        // Display filenames on upload selection
        function displayFilename(input, labelId) {
            const label = document.getElementById(labelId);
            if (input.files && input.files.length > 0) {
                label.innerText = "📁 Selected: " + input.files[0].name;
            } else {
                label.innerText = "";
            }
        }

        // Dynamic Captcha reloader
        function refreshCaptcha() {
            fetch('register.php?refresh_captcha=1')
                .then(response => response.text())
                .then(text => {
                    document.getElementById('captchaBox').innerText = text;
                    document.getElementById('captcha').value = '';
                });
        }

        // Wizard Flow Interactivity
        let currentStep = 1;
        const totalSteps = 4;

        function showStep(n) {
            const steps = document.getElementsByClassName("register-step");
            
            // Hide all steps first
            for (let i = 0; i < steps.length; i++) {
                steps[i].style.display = "none";
                steps[i].classList.remove("active");
            }
            
            // Show selected step
            steps[n - 1].style.display = "block";
            steps[n - 1].classList.add("active");
            
            // Progress tracker status update
            for (let i = 1; i <= totalSteps; i++) {
                const pstep = document.getElementById("pstep-" + i);
                if (i < n) {
                    pstep.classList.remove("active");
                    pstep.classList.add("completed");
                } else if (i === n) {
                    pstep.classList.add("active");
                    pstep.classList.remove("completed");
                } else {
                    pstep.classList.remove("active");
                    pstep.classList.remove("completed");
                }
            }
            
            // Update line percentage
            const barPercent = ((n - 1) / (totalSteps - 1)) * 100;
            document.getElementById("progressBar").style.width = barPercent + "%";
            
            // Button visibility
            if (n === 1) {
                document.getElementById("prevBtn").style.display = "none";
                document.getElementById("signinLink").style.display = "flex";
            } else {
                document.getElementById("prevBtn").style.display = "inline-flex";
                document.getElementById("signinLink").style.display = "none";
            }
            
            if (n === totalSteps) {
                document.getElementById("nextBtn").innerText = "Register Account 🚀";
                document.getElementById("nextBtn").className = "btn-wizard btn-wizard-primary";
            } else {
                document.getElementById("nextBtn").innerText = "Next Step ➡️";
                document.getElementById("nextBtn").className = "btn-wizard btn-wizard-secondary";
            }
        }

        // Allows users to jump to previous step nodes naturally for high-end feel
        function jumpToStep(target) {
            if (target < currentStep) {
                const steps = document.getElementsByClassName("register-step");
                steps[currentStep - 1].classList.remove("active");
                currentStep = target;
                showStep(currentStep);
            } else if (target > currentStep) {
                // If trying to skip forward, validate step-by-step
                let temp = currentStep;
                while (temp < target) {
                    if (!validateStep(temp)) return;
                    temp++;
                }
                const steps = document.getElementsByClassName("register-step");
                steps[currentStep - 1].classList.remove("active");
                currentStep = target;
                showStep(currentStep);
            }
        }

        function validateStep(n) {
            let valid = true;
            const stepDiv = document.getElementById("step-" + n);
            const requiredFields = stepDiv.querySelectorAll("[required]");
            
            // Visual error shaking animation for empty fields
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add("animate-shake-field");
                    
                    // Reset styling after 1.5 seconds
                    setTimeout(() => {
                        field.classList.remove("animate-shake-field");
                    }, 1500);
                } else {
                    field.classList.remove("animate-shake-field");
                }
            });
            
            // Custom validations for step 1
            if (n === 1) {
                const password = document.getElementById("password").value;
                const confirm = document.getElementById("confirm_password").value;
                if (password && confirm && password !== confirm) {
                    valid = false;
                    alert("Passwords do not match!");
                    document.getElementById("confirm_password").classList.add("animate-shake-field");
                }
            }
            
            // Custom validations for step 4 (file checks, confirming accounts)
            if (n === 4) {
                const passbookFile = document.getElementById("passbook_image").files.length;
                const idFile = document.getElementById("id_proof_image").files.length;
                
                if (passbookFile === 0) {
                    valid = false;
                    alert("Please upload your copy of Passbook / Cancelled Check.");
                }
                if (idFile === 0) {
                    valid = false;
                    alert("Please upload your copy of ID proof.");
                }
                
                const accNo = document.getElementById("bank_account_no").value;
                const confAccNo = document.getElementById("confirm_account_no").value;
                if (accNo !== confAccNo) {
                    valid = false;
                    alert("Bank Account Numbers do not match!");
                    document.getElementById("confirm_account_no").classList.add("animate-shake-field");
                }
            }
            
            return valid;
        }

        function nextPrev(n) {
            const steps = document.getElementsByClassName("register-step");
            
            // If going forward, validate first
            if (n === 1 && !validateStep(currentStep)) return false;
            
            // Hide current step
            steps[currentStep - 1].classList.remove("active");
            
            // Increment/decrement step
            currentStep += n;
            
            // If completed steps, submit form!
            if (currentStep > totalSteps) {
                document.getElementById("wizardForm").submit();
                return false;
            }
            
            // Show new step
            showStep(currentStep);
        }
        // Particles JS Logic
        const pc = document.getElementById('particles');
        if (pc) {
            for (let i = 0; i < 20; i++) {
                const p = document.createElement('div');
                p.className = 'p-light';
                const s = Math.random() * 8 + 4;
                p.style.cssText = `left:${Math.random()*100}%;width:${s}px;height:${s}px;animation-duration:${Math.random()*14+8}s;animation-delay:${Math.random()*12}s;`;
                pc.appendChild(p);
            }
        }

        // Sprouts JS Logic
        const sc = document.getElementById('sprouts');
        if (sc) {
            const em = ['🌱','🌿','🍃','🌾','🌻','🥬','🌽'];
            for (let i = 0; i < 18; i++) {
                const s = document.createElement('div');
                s.className = 'sprout-light';
                s.textContent = em[Math.floor(Math.random()*em.length)];
                s.style.cssText = `left:${i*5.8}%;bottom:${Math.random()*20}px;font-size:${Math.random()*12+14}px;animation-duration:${Math.random()*3+2}s;animation-delay:${Math.random()*2}s;`;
                sc.appendChild(s);
            }
        }
    </script>

</body>
</html>