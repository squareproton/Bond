<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class Time implements RandomInterface
{
    public $random;
    public function __construct( $min, $max, $n, $direction = SORT_NUMERIC )
    {
        $range = new Range( $min, $max );
        $times = array();
        while( $n-- > 0 ) {
            $times[] = $range();
        }
        asort( $times, $direction );
        $this->random = new ArrayLoop( $times );
    }
    public function __invoke()
    {
        return $this->random->__invoke();
    }
}