<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

// See, http://en.wikipedia.org/wiki/Normal_distribution#Generating_values_from_normal_distribution
// Box-Muller method
class NormalDistribution implements RandomInterface
{
    const PI = 3.1415926535898;
    const INTEGER = 'INT';
    protected $mean;
    protected $stdDev;
    protected $format;
    private $last;
    function __construct( $mean, $stdDev, $format = self::INTEGER )
    {
        if( !is_numeric( $mean ) || !is_numeric( $stdDev ) ) {
            throw new \Exception("Expecting numbers");
        }
        $this->mean = $mean;
        $this->stdDev = abs( $stdDev );
        $this->format = $format;
    }
    function __invoke( $min = null, $max = null )
    {
        $output = $this->mean + $this->stdDev*((sqrt(-2 * log($this->random())) * cos(2 * self::PI * $this->random())) * 0.5);
        if( is_numeric( $min ) and $output < $min ) {
            return $this->last = $this->__invoke( $min, $max );
        }
        if( is_numeric( $max ) and $output > $max ) {
            return $this->last = $this->__invoke( $min, $max );
        }
        if( $this->format === self::INTEGER ) {
            $output = (int) round( $output, 1 );
        }
        return $this->last = $output;
    }
    public function last()
    {
        return $this->last;
    }
    private function random()
    {
        return mt_rand() / mt_getrandmax();
    }
}