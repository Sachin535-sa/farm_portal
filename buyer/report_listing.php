<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit();
}

if (!isset($_POST['crop_id'])) {
    echo json_encode(["success" => false, "error" => "Missing listing ID."]);
    exit();
}

$crop_id = mysqli_real_escape_string($conn, $_POST['crop_id']);

// Update reports count in DB
$sql_update = "UPDATE crops SET reports_count = reports_count + 1 WHERE id = '$crop_id'";
if (mysqli_query($conn, $sql_update)) {
    // Check if new reports count >= 3
    $sql_check = "SELECT reports_count FROM crops WHERE id = '$crop_id'";
    $res_check = mysqli_query($conn, $sql_check);
    if ($res_check && mysqli_num_rows($res_check) > 0) {
        $crop = mysqli_fetch_assoc($res_check);
        $new_count = (int)$crop['reports_count'];
        
        if ($new_count >= 3) {
            // Flag listing as fake listing automatically
            mysqli_query($conn, "UPDATE crops SET is_flagged = 1, flag_reason = 'Fake Listing: Reported by multiple community members.' WHERE id = '$crop_id'");
        }
        
        echo json_encode(["success" => true, "reports_count" => $new_count]);
        exit();
    }
}

echo json_encode(["success" => false, "error" => "Database update failed."]);
?>
