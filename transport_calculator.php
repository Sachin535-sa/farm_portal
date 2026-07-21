<?php
/**
 * AgroNava - Advanced Transportation & Dynamic Delivery Pricing Engine
 * Production-grade multi-node logistics calculation engine.
 */

class TransportCalculator {
    private $baseFee;
    private $ratePerKm;
    private $ratePerKg;

    public function __construct($baseFee = 30, $ratePerKm = 8, $ratePerKg = 2) {
        $this->baseFee = $baseFee;
        $this->ratePerKm = $ratePerKm;
        $this->ratePerKg = $ratePerKg;
    }

    /**
     * Haversine formula to compute great-circle distance between coordinates
     */
    public function getDistance($lat1, $lon1, $lat2, $lon2) {
        $R = 6371; // Earth radius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    /**
     * OSRM HTTP backend routing client with 2-second timeout.
     * Fetches driving route distance and geometry.
     */
    public function getOSRMRoute($lat1, $lon1, $lat2, $lon2) {
        $url = "https://router.project-osrm.org/route/v1/driving/{$lon1},{$lat1};{$lon2},{$lat2}?overview=full&geometries=geojson";
        
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2.0, // 2 seconds limit
                'header' => "User-Agent: AgroNava-LogisticsEngine/2.0\r\n"
            ]
        ]);
        
        $response = @file_get_contents($url, false, $ctx);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['code']) && $data['code'] === 'Ok' && isset($data['routes'][0])) {
                return $data['routes'][0];
            }
        }
        return null;
    }

    /**
     * Determine packaging type and surcharge based on crop name.
     */
    public function getPackagingDetails($cropName) {
        $name = strtolower($cropName);
        if (preg_match('/(milk|dairy|butter|cheese)/', $name)) {
            return [
                'type' => 'Cold Storage Box',
                'fee' => 100,
                'icon' => '❄️'
            ];
        } elseif (preg_match('/(tomato|potato|onion|chili|vegetable|veg)/', $name)) {
            return [
                'type' => 'Ventilated Box',
                'fee' => 40,
                'icon' => '📦'
            ];
        } elseif (preg_match('/(apple|mango|banana|orange|grape|fruit)/', $name)) {
            return [
                'type' => 'Foam Packing',
                'fee' => 30,
                'icon' => '🍎'
            ];
        } else {
            return [
                'type' => 'Jute Bag',
                'fee' => 20,
                'icon' => '🌾'
            ];
        }
    }

    /**
     * Finds the nearest collection center using Haversine lookup.
     */
    public function findNearestCollectionCenter($lat, $lng) {
        global $conn;
        $cc_res = mysqli_query($conn, "SELECT * FROM collection_centers");
        $nearestCC = null;
        $minCCDist = 999999;
        if ($cc_res) {
            while ($cc = mysqli_fetch_assoc($cc_res)) {
                $dist = $this->getDistance($lat, $lng, $cc['latitude'], $cc['longitude']);
                if ($dist < $minCCDist) {
                    $minCCDist = $dist;
                    $nearestCC = $cc;
                }
            }
        }
        return $nearestCC;
    }

    /**
     * Finds the nearest warehouse to a given collection center.
     */
    public function findNearestWarehouse($centerId) {
        global $conn;
        $cc_q = mysqli_query($conn, "SELECT latitude, longitude FROM collection_centers WHERE id = '" . intval($centerId) . "' LIMIT 1");
        if (!$cc_q || mysqli_num_rows($cc_q) == 0) return null;
        $cc = mysqli_fetch_assoc($cc_q);
        
        $wh_res = mysqli_query($conn, "SELECT * FROM warehouses");
        $nearestWH = null;
        $minWHDist = 999999;
        if ($wh_res) {
            while ($wh = mysqli_fetch_assoc($wh_res)) {
                $dist = $this->getDistance($cc['latitude'], $cc['longitude'], $wh['latitude'], $wh['longitude']);
                if ($dist < $minWHDist) {
                    $minWHDist = $dist;
                    $nearestWH = $wh;
                }
            }
        }
        return $nearestWH;
    }

    /**
     * Recommends the optimal delivery vehicle based on weight, distance, and temperature requirements.
     * Supports both new signature recommendVehicle($weight, $volume, $productType, $distance)
     * and old signature recommendVehicle($conn, $weight, $distance, $cropName) for backwards compatibility.
     */
    public function recommendVehicle($weightOrConn, $volumeOrWeight = 0, $productTypeOrDistance = '', $distanceOrCrop = '') {
        global $conn;
        $weight = 0.0;
        $distance = 15.0;
        $cropName = '';

        if ($weightOrConn instanceof mysqli || (is_resource($weightOrConn) && get_resource_type($weightOrConn) === 'mysql link')) {
            // Old signature: recommendVehicle($conn, $weightKg, $distanceKm, $cropName)
            $weight = floatval($volumeOrWeight);
            $distance = floatval($productTypeOrDistance);
            $cropName = strval($distanceOrCrop);
        } else {
            // New signature: recommendVehicle($weight, $volume, $productType, $distance)
            $weight = floatval($weightOrConn);
            $cropName = strval($productTypeOrDistance); // productType maps to cropName checks
            $distance = floatval($distanceOrCrop ?: 15.0);
        }

        $name = strtolower($cropName);
        
        // Cold chain validation
        if (preg_match('/(milk|dairy|butter|cheese)/', $name)) {
            $q = mysqli_query($conn, "SELECT * FROM vehicles WHERE name = 'cold_storage_truck' LIMIT 1");
            if ($q && $v = mysqli_fetch_assoc($q)) {
                return $v;
            }
        }

        // Standard rules based on weight and distance
        $v_name = 'bike';
        if ($weight <= 20) {
            if ($distance <= 15) {
                $v_name = 'electric_vehicle';
            } else {
                $v_name = 'bike';
            }
        } elseif ($weight <= 150) {
            $v_name = 'auto';
        } elseif ($weight <= 750) {
            $v_name = 'pickup_van';
        } elseif ($weight <= 1500) {
            $v_name = 'mini_truck';
        } else {
            $v_name = 'heavy_truck';
        }

        $q = mysqli_query($conn, "SELECT * FROM vehicles WHERE name = '$v_name' LIMIT 1");
        if ($q && $v = mysqli_fetch_assoc($q)) {
            return $v;
        }

        // Fallback vehicle info if DB query fails
        return [
            'name' => 'bike',
            'display_name' => '🛵 2-Wheeler (Motorcycle)',
            'multiplier' => 1.0,
            'max_weight' => 20.0,
            'cost_per_km' => 1.50,
            'fuel_type' => 'petrol'
        ];
    }

    /**
     * Estimates travel duration in minutes based on distance and road speed.
     */
    public function estimateETA($distance, $roadCondition) {
        global $conn;
        $road_q = mysqli_query($conn, "SELECT * FROM road_conditions WHERE condition_name = '" . mysqli_real_escape_string($conn, $roadCondition) . "' LIMIT 1");
        $road_cond = mysqli_fetch_assoc($road_q);
        $est_speed = $road_cond ? floatval($road_cond['estimated_speed']) : 40.00;

        $travel_hours = $distance / $est_speed;
        return round($travel_hours * 60);
    }

    /**
     * Resolves the multi-stage logistics nodes (Farmer -> CC -> WH -> Buyer)
     */
    public function resolveLogisticsNodes($conn, $growerLat, $growerLng, $buyerLat, $buyerLng, $overrideCCId = 0, $overrideWHId = 0) {
        $nearestCC = null;
        if ($overrideCCId > 0) {
            $cc_q = mysqli_query($conn, "SELECT * FROM collection_centers WHERE id = '" . intval($overrideCCId) . "' LIMIT 1");
            $nearestCC = mysqli_fetch_assoc($cc_q);
        }
        if (!$nearestCC) {
            $nearestCC = $this->findNearestCollectionCenter($growerLat, $growerLng);
        }

        $nearestWH = null;
        if ($overrideWHId > 0) {
            $wh_q = mysqli_query($conn, "SELECT * FROM warehouses WHERE id = '" . intval($overrideWHId) . "' LIMIT 1");
            $nearestWH = mysqli_fetch_assoc($wh_q);
        }
        if (!$nearestWH && $nearestCC) {
            $nearestWH = $this->findNearestWarehouse($nearestCC['id']);
        }

        $minCCDist = $nearestCC ? $this->getDistance($growerLat, $growerLng, $nearestCC['latitude'], $nearestCC['longitude']) : 0.0;
        $minWHDist = ($nearestCC && $nearestWH) ? $this->getDistance($nearestCC['latitude'], $nearestCC['longitude'], $nearestWH['latitude'], $nearestWH['longitude']) : 0.0;

        return [
            'collection_center' => $nearestCC,
            'warehouse' => $nearestWH,
            'distance_farm_to_cc' => round($minCCDist, 2),
            'distance_cc_to_wh' => round($minWHDist, 2)
        ];
    }

    /**
     * Dynamic Price Calculation Wrapper (Service Delegate)
     */
    public function calculatePrice($params) {
        global $conn;
        return $this->calculateDynamicDelivery($conn, $params);
    }

    /**
     * Dynamic currency rate helper for future USD/EUR expansion (INR by default)
     */
    public function convertCurrency($amount, $targetCurrency = 'INR') {
        $rates = [
            'INR' => 1.0,
            'USD' => 0.012,
            'EUR' => 0.011,
            'GBP' => 0.009
        ];
        $rate = $rates[$targetCurrency] ?? 1.0;
        return $amount * $rate;
    }

    /**
     * Legacy Wrapper 1
     */
    public function calculateByDistance($distanceKm) {
        return $this->baseFee + ($distanceKm * $this->ratePerKm);
    }

    /**
     * Legacy Wrapper 2
     */
    public function calculateByWeight($weightKg) {
        return $weightKg * $this->ratePerKg;
    }

    /**
     * Legacy Advanced Combined Formula
     */
    public function calculateAdvanced($distanceKm, $weightKg, $cropName = '') {
        global $conn;
        if (isset($conn)) {
            $result = $this->calculateDynamicDelivery($conn, [
                'distance_km' => $distanceKm,
                'weight_kg' => $weightKg,
                'crop_name' => $cropName,
                'vehicle_type' => 'bike',
                'delivery_priority' => 'standard',
                'road_condition' => 'city_road',
                'location_type' => 'urban'
            ]);
            return $result['final_delivery_fee'];
        }
        $pkg = $this->getPackagingDetails($cropName);
        return $this->baseFee + ($distanceKm * $this->ratePerKm) + ($weightKg * $this->ratePerKg) + $pkg['fee'];
    }

    /**
     * Production-grade Dynamic Logistics Pricing Engine
     */
    public function calculateDynamicDelivery($conn, $params) {
        // Retrieve base pricing rules row
        $rules_q = mysqli_query($conn, "SELECT * FROM delivery_pricing_rules LIMIT 1");
        $rules = mysqli_fetch_assoc($rules_q);

        // Parameters
        $crop_id = isset($params['crop_id']) ? intval($params['crop_id']) : 0;
        $selected_vehicle = isset($params['vehicle_type']) ? $params['vehicle_type'] : '';
        $priority = isset($params['delivery_priority']) ? $params['delivery_priority'] : 'standard';
        $road = isset($params['road_condition']) ? $params['road_condition'] : 'city_road';
        $zone = isset($params['location_type']) ? $params['location_type'] : 'urban';
        $order_value = floatval(isset($params['order_value']) ? $params['order_value'] : 0.0);
        $weather = isset($params['weather']) ? $params['weather'] : 'clear';

        // Resolve weight from weight_kg or quantity
        $weight = floatval(isset($params['weight_kg']) ? $params['weight_kg'] : (isset($params['quantity']) ? $params['quantity'] : 1.0));
        $crop_name = isset($params['crop_name']) ? $params['crop_name'] : '';

        // Resolve Crop Price from DB if crop_id is provided and order_value is not passed
        if ($crop_id > 0 && $order_value <= 0) {
            $crop_q = mysqli_query($conn, "SELECT price, crop_name FROM crops WHERE id = '$crop_id' LIMIT 1");
            if ($crop_q && $crop_row = mysqli_fetch_assoc($crop_q)) {
                $crop_price = floatval($crop_row['price']);
                $order_value = $crop_price * $weight;
                if (empty($crop_name)) {
                    $crop_name = $crop_row['crop_name'];
                }
            }
        }

        // Coords
        $growerLat = floatval(isset($params['grower_lat']) ? $params['grower_lat'] : 30.7046);
        $growerLng = floatval(isset($params['grower_lng']) ? $params['grower_lng'] : 76.7179);
        $buyerLat = floatval(isset($params['buyer_lat']) ? $params['buyer_lat'] : 30.7333);
        $buyerLng = floatval(isset($params['buyer_lng']) ? $params['buyer_lng'] : 76.7794);

        // Try loading grower coords from DB if grower coords are at default and crop_id exists
        if ($growerLat == 30.7046 && $growerLng == 76.7179 && $crop_id > 0) {
            $grower_coords_q = mysqli_query($conn, "SELECT u.latitude, u.longitude FROM crops c JOIN users u ON c.farmer_id = u.id WHERE c.id = '$crop_id' LIMIT 1");
            if ($grower_coords_q && $grower_row = mysqli_fetch_assoc($grower_coords_q)) {
                if (!empty($grower_row['latitude']) && floatval($grower_row['latitude']) != 0) {
                    $growerLat = floatval($grower_row['latitude']);
                    $growerLng = floatval($grower_row['longitude']);
                }
            }
        }

        // Resolve logistics segment distances (multi-stage)
        $overrideCCId = isset($params['collection_center_id']) ? intval($params['collection_center_id']) : 0;
        $overrideWHId = isset($params['warehouse_id']) ? intval($params['warehouse_id']) : 0;
        $nodes = $this->resolveLogisticsNodes($conn, $growerLat, $growerLng, $buyerLat, $buyerLng, $overrideCCId, $overrideWHId);
        $dist_farm_to_cc = $nodes['distance_farm_to_cc'];
        $dist_cc_to_wh = $nodes['distance_cc_to_wh'];
        
        $whLat = $nodes['warehouse']['latitude'] ?? 30.7333;
        $whLng = $nodes['warehouse']['longitude'] ?? 76.7794;

        // Dynamic OSRM integration for final segment (Warehouse to Buyer)
        $routeGeoJSON = null;
        if (isset($params['distance_km']) && floatval($params['distance_km']) > 0) {
            $distance_wh_to_buyer = floatval($params['distance_km']);
        } else {
            $osrmRoute = $this->getOSRMRoute($whLat, $whLng, $buyerLat, $buyerLng);
            if ($osrmRoute) {
                $distance_wh_to_buyer = floatval($osrmRoute['distance'] / 1000.0);
                $routeGeoJSON = json_encode($osrmRoute['geometry']);
            } else {
                // Fallback: Haversine distance with winding factor 1.35
                $directDist = $this->getDistance($whLat, $whLng, $buyerLat, $buyerLng);
                $distance_wh_to_buyer = $directDist * 1.35;
            }
        }
        
        // Total Road Distance (Grower -> CC -> WH -> Buyer)
        $total_distance = $dist_farm_to_cc + $dist_cc_to_wh + $distance_wh_to_buyer;

        // Auto vehicle recommendation
        $rec_vehicle = $this->recommendVehicle($weight, 0, $crop_name, $total_distance);
        
        // Reconcile vehicle choice
        $vehicle_code = !empty($selected_vehicle) ? $selected_vehicle : $rec_vehicle['name'];
        $vehicle_q = mysqli_query($conn, "SELECT * FROM vehicles WHERE name = '$vehicle_code' LIMIT 1");
        $vehicle = mysqli_fetch_assoc($vehicle_q) ?: $rec_vehicle;

        // 1. Base Fee
        $base_fee = floatval($rules['base_fee']);

        // 2. Distance Charge
        $distance_charge = ($total_distance * floatval($rules['per_km_rate'])) + ($total_distance * floatval($vehicle['cost_per_km']));

        // 3. Weight Slab Surcharge
        $weight_surcharge = 0.0;
        $slabs = json_decode($rules['weight_slabs_json'], true) ?: [];
        foreach ($slabs as $slab) {
            if ($weight >= $slab['min'] && $weight <= $slab['max']) {
                $weight_surcharge = floatval($slab['surcharge']);
                break;
            }
        }

        // 4. Vehicle Multiplier
        $vehicle_multiplier = floatval($vehicle['multiplier']);

        // 5. Fuel Surcharge
        $fuel_type = $vehicle['fuel_type'];
        $fuel_q = mysqli_query($conn, "SELECT * FROM fuel_prices WHERE fuel_type = '$fuel_type' LIMIT 1");
        $fuel = mysqli_fetch_assoc($fuel_q);
        
        $fuel_adjustment = 0.0;
        if ($fuel) {
            $price_delta = floatval($fuel['current_price']) - floatval($fuel['base_price']);
            if ($price_delta > 0) {
                $fuel_adjustment = $price_delta * floatval($fuel['fuel_adjustment_factor']) * $total_distance;
            }
        }

        // 6. Road Conditions Surcharges & Speeds
        $road_q = mysqli_query($conn, "SELECT * FROM road_conditions WHERE condition_name = '$road' LIMIT 1");
        $road_cond = mysqli_fetch_assoc($road_q);
        $road_multiplier = $road_cond ? floatval($road_cond['multiplier']) : 1.00;
        $road_flat = $road_cond ? floatval($road_cond['extra_charge']) : 0.00;
        $est_speed = $road_cond ? floatval($road_cond['estimated_speed']) : 40.00;

        // 7. Delivery Zone Multipliers
        $zone_q = mysqli_query($conn, "SELECT * FROM delivery_zones WHERE zone_name = '$zone' LIMIT 1");
        $zone_info = mysqli_fetch_assoc($zone_q);
        $zone_multiplier = $zone_info ? floatval($zone_info['multiplier']) : 1.00;
        $zone_flat = $zone_info ? floatval($zone_info['flat_charge']) : 0.00;

        // 8. Delivery Priority Charges
        $priority_flat = 0.0;
        $priority_multiplier = 1.0;
        if ($priority === 'express') {
            $priority_flat = 45.00;
            $priority_multiplier = 1.25;
        } elseif ($priority === 'urgent') {
            $priority_flat = 90.00;
            $priority_multiplier = 1.50;
        } elseif ($priority === 'same_day') {
            $priority_flat = 25.00;
            $priority_multiplier = 1.10;
        }

        // 9. Packaging Details
        $pkg = $this->getPackagingDetails($crop_name);
        $packaging_fee = floatval($pkg['fee']);

        // 10. Toll & Seasonal Surcharges
        $toll_charges = floatval($rules['toll_charges']);
        $seasonal_flat = floatval($rules['seasonal_charges_flat']);
        $seasonal_multiplier = floatval($rules['seasonal_charges_multiplier']);

        // 11. Weather Factor
        $weather_flat = 0.0;
        $weather_multiplier = 1.0;
        $weather_factors = json_decode($rules['weather_factors_json'], true) ?: [];
        if (isset($weather_factors[$weather])) {
            $weather_multiplier = floatval($weather_factors[$weather]['multiplier']);
            $weather_flat = floatval($weather_factors[$weather]['flat']);
        }

        // TODO: AI Hook - dynamic surge pricing demand multiplier
        $ai_demand_multiplier = 1.0; 

        // Math formulation:
        $subtotal = $base_fee + $distance_charge + $weight_surcharge;
        $multipliers = $vehicle_multiplier * $road_multiplier * $zone_multiplier * $priority_multiplier * $seasonal_multiplier * $weather_multiplier * $ai_demand_multiplier;
        
        $raw_calculated = $subtotal * $multipliers;
        $raw_calculated += $road_flat + $zone_flat + $priority_flat + $fuel_adjustment + $packaging_fee + $toll_charges + $seasonal_flat + $weather_flat;

        // Enforce Minimum / Maximum limits
        $minimum_enforced = false;
        $maximum_enforced = false;
        $final_delivery = $raw_calculated;
        
        $min_fee = floatval($rules['minimum_fee']);
        $max_fee = floatval($rules['maximum_fee']);

        if ($final_delivery < $min_fee) {
            $final_delivery = $min_fee;
            $minimum_enforced = true;
        }
        if ($final_delivery > $max_fee) {
            $final_delivery = $max_fee;
            $maximum_enforced = true;
        }

        // Free Delivery check
        $free_delivery_applied = false;
        $free_threshold = floatval($rules['free_delivery_threshold']);
        if ($free_threshold > 0 && $order_value >= $free_threshold) {
            $free_delivery_applied = true;
            $final_delivery_fee = 0.0;
        } else {
            $final_delivery_fee = $final_delivery;
        }

        // ETA calculation:
        $travel_minutes = $this->estimateETA($total_distance, $road);
        
        // Priority modifications to speed/ETA
        if ($priority === 'urgent') {
            $travel_minutes = round($travel_minutes * 0.7);
        } elseif ($priority === 'express') {
            $travel_minutes = round($travel_minutes * 0.85);
        }
        
        // TODO: AI Hook - real-time traffic delay factor
        $traffic_delay_minutes = 0; 
        $travel_minutes += $traffic_delay_minutes;

        $eta_time_str = "";
        if ($travel_minutes < 60) {
            $eta_time_str = $travel_minutes . " mins";
        } else {
            $hours = floor($travel_minutes / 60);
            $mins = $travel_minutes % 60;
            $eta_time_str = $hours . "h " . $mins . "m";
        }

        // TODO: AI Hook - carbon emission estimator (kg CO2e)
        // Average carbon intensity: petrol = 0.12 kg/km, diesel = 0.15 kg/km, EV = 0.015 kg/km
        $co2_factor = 0.15;
        if ($fuel_type === 'petrol') $co2_factor = 0.12;
        if ($fuel_type === 'cng') $co2_factor = 0.09;
        if ($fuel_type === 'electric') $co2_factor = 0.015;
        $carbon_footprint_kg = round($co2_factor * $total_distance, 2);

        $grand_total = $order_value + $final_delivery_fee;

        return [
            'base_fee' => round($base_fee, 2),
            'distance_farm_to_cc' => $dist_farm_to_cc,
            'distance_cc_to_wh' => $dist_cc_to_wh,
            'distance_wh_to_buyer' => round($distance_wh_to_buyer, 2),
            'total_distance_km' => round($total_distance, 2),
            'distance_cost' => round($distance_charge, 2),
            'weight_kg' => round($weight, 2),
            'weight_surcharge' => round($weight_surcharge, 2),
            'subtotal' => round($subtotal, 2),
            'recommended_vehicle' => $rec_vehicle['name'],
            'recommended_vehicle_display' => $rec_vehicle['display_name'],
            'vehicle_type' => $vehicle['name'],
            'vehicle_display' => $vehicle['display_name'],
            'vehicle_multiplier' => $vehicle_multiplier,
            'fuel_type' => $fuel_type,
            'fuel_adjustment' => round($fuel_adjustment, 2),
            'road_condition' => $road,
            'road_multiplier' => $road_multiplier,
            'road_flat' => $road_flat,
            'delivery_zone' => $zone,
            'zone_multiplier' => $zone_multiplier,
            'zone_flat' => $zone_flat,
            'delivery_priority' => $priority,
            'priority_multiplier' => $priority_multiplier,
            'priority_flat' => $priority_flat,
            'packaging_type' => $pkg['type'],
            'packaging_fee' => round($packaging_fee, 2),
            'toll_charges' => round($toll_charges, 2),
            'seasonal_flat' => round($seasonal_flat, 2),
            'seasonal_multiplier' => $seasonal_multiplier,
            'weather' => $weather,
            'weather_flat' => $weather_flat,
            'weather_multiplier' => $weather_multiplier,
            'raw_calculated' => round($raw_calculated, 2),
            'minimum_fee' => round($min_fee, 2),
            'maximum_fee' => round($max_fee, 2),
            'minimum_enforced' => $minimum_enforced,
            'maximum_enforced' => $maximum_enforced,
            'free_delivery_threshold' => round($free_threshold, 2),
            'free_delivery_applied' => $free_delivery_applied,
            'final_delivery_fee' => round($final_delivery_fee, 2),
            'estimated_delivery_time' => $eta_time_str,
            'travel_minutes' => $travel_minutes,
            'carbon_footprint_kg' => $carbon_footprint_kg,
            'warehouse_id' => $nodes['warehouse']['id'] ?? 1,
            'warehouse_name' => $nodes['warehouse']['name'] ?? 'Chandigarh Central Storage',
            'collection_center_id' => $nodes['collection_center']['id'] ?? 1,
            'collection_center_name' => $nodes['collection_center']['name'] ?? 'Mohali Agribusiness Center',
            'order_value' => round($order_value, 2),
            'grower_lat' => $growerLat,
            'grower_lng' => $growerLng,
            'collection_center_lat' => floatval($nodes['collection_center']['latitude'] ?? 30.7046),
            'collection_center_lng' => floatval($nodes['collection_center']['longitude'] ?? 76.7179),
            'warehouse_lat' => floatval($nodes['warehouse']['latitude'] ?? 30.7333),
            'warehouse_lng' => floatval($nodes['warehouse']['longitude'] ?? 76.7794),
            'grand_total' => round($grand_total, 2),
            'route_geojson' => $routeGeoJSON
        ];
    }
}
?>
