<?php
session_start();

if(isset($_SESSION['user_id'])){
    if($_SESSION['role'] == "farmer"){
        header("Location: ../farmer/orders.php");
        exit();
    } else {
        header("Location: my_orders.php");
        exit();
    }
} else {
    header("Location: ../auth/login.php");
    exit();
}
?>