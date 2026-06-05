<?php
session_start();
include("db.php");

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $update_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = '$user_id'";
    if (mysqli_query($conn, $update_sql)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
}
exit();
?>
