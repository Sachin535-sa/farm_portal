<?php
session_start();
include("../config/db.php");

// Ensure buyer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "buyer") {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized access"]); 
    exit();
}

$buyer_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Invalid request method"]);
    exit();
}

$crop_id   = intval($_POST['crop_id'] ?? 0);
$bid_amount = intval($_POST['bid_amount'] ?? 0);

if ($crop_id <= 0 || $bid_amount <= 0) {
    echo json_encode(["error" => "Missing or invalid parameters"]);
    exit();
}

// Fetch auction details
$crop_res = mysqli_query($conn, "SELECT is_auction, auction_end_time, current_bid FROM crops WHERE id='$crop_id' AND is_auction=1");
if (!$crop_res || mysqli_num_rows($crop_res) === 0) {
    echo json_encode(["error" => "Auction not found or not active"]);
    exit();
}
$crop = mysqli_fetch_assoc($crop_res);

// Check auction timing
if (strtotime($crop['auction_end_time']) < time()) {
    echo json_encode(["error" => "Auction has already ended"]);
    exit();
}

$current_bid = intval($crop['current_bid']);
if ($current_bid > 0 && $bid_amount <= $current_bid) {
    echo json_encode(["error" => "Bid must exceed current highest bid ($current_bid)"]);
    exit();
}

// Record the bid
$insert_bid = mysqli_query($conn, "INSERT INTO bids (crop_id, buyer_id, bid_amount) VALUES ('$crop_id', '$buyer_id', '$bid_amount')");
if (!$insert_bid) {
    echo json_encode(["error" => "Failed to record bid: " . mysqli_error($conn)]);
    exit();
}

// Update crop with new highest bid
mysqli_query($conn, "UPDATE crops SET current_bid='$bid_amount', highest_bidder_id='$buyer_id' WHERE id='$crop_id'");

echo json_encode(["success" => true, "new_bid" => $bid_amount]);
?>
