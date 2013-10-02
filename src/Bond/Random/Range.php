<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class Range implements RandomInterface
{
    public $min;
    public $max;
    public $step;
    public function __construct( $min, $max, $step = 1 )
    {
        $this->min = (int) $min;
        $this->max = (int) $max;
        $this->step = max( (int) $step, 1 );
    }
    public function __invoke()
    {
        $output = rand( $this->min, $this->max );

        if( $this->step > 1 ) {
            $quotient = round(
                $output / $this->step,
                0,
                PHP_ROUND_HALF_EVEN
            );
            $output = $this->step * $quotient;
        }
        return $output;
    }
}