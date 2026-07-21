<?php
/**
 * AgroNava - Logistics & Delivery Pricing REST API Router
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include("../config/db.php");
include("../transport_calculator.php");

$action = isset($_GET['action']) ? $_GET['action'] : '';

$calculator = new TransportCalculator();

switch ($action) {
    case 'rules':
        $rules_q = mysqli_query($conn, "SELECT * FROM delivery_pricing_rules LIMIT 1");
        $rules = mysqli_fetch_assoc($rules_q);
        
        $fuels_q = mysqli_query($conn, "SELECT * FROM fuel_prices");
        $fuels = [];
        while($f = mysqli_fetch_assoc($fuels_q)) {
            $fuels[] = $f;
        }

        $vehicles_q = mysqli_query($conn, "SELECT * FROM vehicles");
        $vehicles = [];
        while($v = mysqli_fetch_assoc($vehicles_q)) {
            $vehicles[] = $v;
        }

        $zones_q = mysqli_query($conn, "SELECT * FROM delivery_zones");
        $zones = [];
        while($z = mysqli_fetch_assoc($zones_q)) {
            $zones[] = $z;
        }

        $roads_q = mysqli_query($conn, "SELECT * FROM road_conditions");
        $roads = [];
        while($r = mysqli_fetch_assoc($roads_q)) {
            $roads[] = $r;
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'core_rules' => $rules,
                'fuel_index' => $fuels,
                'vehicles' => $vehicles,
                'zones' => $zones,
                'road_conditions' => $roads
            ]
        ]);
        break;

    case 'calculate':
        $params = [
            'distance_km' => floatval($_GET['distance_km'] ?? 15.0),
            'weight_kg' => floatval($_GET['weight_kg'] ?? 1.0),
            'vehicle_type' => $_GET['vehicle_type'] ?? '',
            'delivery_priority' => $_GET['delivery_priority'] ?? 'standard',
            'road_condition' => $_GET['road_condition'] ?? 'city_road',
            'location_type' => $_GET['location_type'] ?? 'urban',
            'crop_name' => $_GET['crop_name'] ?? '',
            'order_value' => floatval($_GET['order_value'] ?? 0.0),
            'weather' => $_GET['weather'] ?? 'clear'
        ];

        try {
            $result = $calculator->calculateDynamicDelivery($conn, $params);
            echo json_encode([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        break;

    case 'recommend_vehicle':
        $weight = floatval($_GET['weight_kg'] ?? 1.0);
        $distance = floatval($_GET['distance_km'] ?? 15.0);
        $crop_name = $_GET['crop_name'] ?? '';

        try {
            $vehicle = $calculator->recommendVehicle($conn, $weight, $distance, $crop_name);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'weight_kg' => $weight,
                    'distance_km' => $distance,
                    'crop_name' => $crop_name,
                    'recommended_vehicle' => $vehicle
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        break;

    case 'analytics':
        $agg_q = mysqli_query($conn, "SELECT 
            AVG(delivery_distance) as avg_dist, 
            AVG(transport_cost) as avg_cost, 
            COUNT(*) as total_deliveries, 
            SUM(fuel_adjustment) as total_fuel_expenses, 
            SUM(transport_cost) as total_revenue
            FROM orders WHERE transport_cost > 0");

        $agg = mysqli_fetch_assoc($agg_q);
        
        $returns_q = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(total_return_cost) as cost FROM return_logistics");
        $returns_info = mysqli_fetch_assoc($returns_q);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_deliveries' => intval($agg['total_deliveries'] ?? 0),
                'logistics_revenue' => floatval($agg['total_revenue'] ?? 0.0),
                'fuel_expenses' => floatval($agg['total_fuel_expenses'] ?? 0.0),
                'avg_distance_km' => floatval($agg['avg_dist'] ?? 0.0),
                'avg_cost' => floatval($agg['avg_cost'] ?? 0.0),
                'returns_count' => intval($returns_info['count'] ?? 0),
                'returns_cost' => floatval($returns_info['cost'] ?? 0.0)
            ]
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Endpoint or action not found. Valid actions: rules, calculate, recommend_vehicle, analytics'
        ]);
        break;
}
?>
