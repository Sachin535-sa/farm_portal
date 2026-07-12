<?php
/**
 * AgriDirect - Transport Cost Calculation System
 * Provides formulas to calculate the exact transport cost based on distance, weight, and packaging.
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
            // Default packaging for grains, seeds, etc.
            return [
                'type' => 'Jute Bag',
                'fee' => 20,
                'icon' => '🌾'
            ];
        }
    }

    /**
     * Calculate dynamic transport cost based on distance
     */
    public function calculateByDistance($distanceKm) {
        return $this->baseFee + ($distanceKm * $this->ratePerKm);
    }

    /**
     * Calculate dynamic delivery cost based on weight
     */
    public function calculateByWeight($weightKg) {
        return $weightKg * $this->ratePerKg;
    }

    /**
     * Advanced Combined Formula:
     * Base Fee + (Distance * KM Rate) + (Weight * KG Rate) + Packaging Surcharge
     */
    public function calculateAdvanced($distanceKm, $weightKg, $cropName = '') {
        $pkg = $this->getPackagingDetails($cropName);
        return $this->baseFee + ($distanceKm * $this->ratePerKm) + ($weightKg * $this->ratePerKg) + $pkg['fee'];
    }
}
?>
