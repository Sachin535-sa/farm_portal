<?php
// File: buyer/calculate_delivery.php
// AJAX endpoint to calculate delivery cost, recommend vehicle, and provide ETA.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../transport_calculator.php';
header('Content-Type: application/json');

// 1. Check for POST JSON payload first
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

// 2. Merge with $_GET and $_POST parameters for flexible integration
$input = array_merge($input, $_GET, $_POST);

if (empty($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No request parameters provided.']);
    exit;
}

$calculator = new TransportCalculator();
try {
    $result = $calculator->calculateDynamicDelivery($conn, $input);
    
    // Support both root level properties (as expected by app.js) and nested data node
    echo json_encode(array_merge(['status' => 'success', 'data' => $result], $result));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
