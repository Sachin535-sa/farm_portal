<?php
/**
 * AgriDirect - Transport Cost Calculation System
 * Provides formulas to calculate the exact transport cost based on distance and weight.
 */

class TransportCalculator {
    private $baseFee;
    private $ratePerKm;
    private $ratePerKg;

    public function __construct($baseFee = 30, $ratePerKm = 5, $ratePerKg = 2) {
        // Default values as per AgriDirect specification
        $this->baseFee = $baseFee;
        $this->ratePerKm = $ratePerKm;
        $this->ratePerKg = $ratePerKg;
    }

    /**
     * Formula 1 - Distance Based
     * Transport Cost = Base Fee + (Distance * Rate Per KM)
     */
    public function calculateByDistance($distanceKm) {
        return $this->baseFee + ($distanceKm * $this->ratePerKm);
    }

    /**
     * Formula 2 - Weight Based
     * Delivery Cost = Weight * Rate Per KG
     */
    public function calculateByWeight($weightKg) {
        return $weightKg * $this->ratePerKg;
    }

    /**
     * Formula 3 - Advanced Combined Formula
     * Total Delivery Cost = (Distance * KM Rate) + (Weight * Weight Rate)
     * *Note: Added base fee for consistency, though original formula didn't explicitly mention it in point 6, 
     * but it's a best practice in logistics. We'll stick to the exact formula: (Distance * KM Rate) + (Weight * Weight Rate)*
     */
    public function calculateAdvanced($distanceKm, $weightKg) {
        return ($distanceKm * $this->ratePerKm) + ($weightKg * $this->ratePerKg);
    }
}
?>
