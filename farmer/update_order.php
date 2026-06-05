<?php
session_start();
include("../config/db.php");

// Session check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "farmer"){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

if(isset($_GET['id']) && isset($_GET['status'])){

    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);

    // Critical Security Check: Ensure this order actually belongs to a crop owned by this farmer
    $check = mysqli_query($conn, "SELECT o.id FROM orders o 
                                  JOIN crops c ON o.crop_id = c.id 
                                  WHERE o.id='$id' AND c.farmer_id='$farmer_id'");

    if(mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE orders SET status='$status' WHERE id='$id'");
    }
}

header("Location: orders.php");
exit();
?>