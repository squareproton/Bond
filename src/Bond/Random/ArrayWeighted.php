<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class ArrayWeighted implements RandomInterface
{
    public $options;
    public $weights;
    public $total;
    function __construct( array $options, array $weights )
    {
        $this->options = array_values( $options );
        $this->weights = array_map( 'intval', $weights );
        $this->total = array_sum( $weights );

        // we got any objects?
        if( count( array_filter( $this->options, 'is_object' ) ) ) {
            return;
        }

        array_multisort( $this->weights, $this->options, SORT_NUMERIC );
        // Weird. Don't seem to be able to reverse the search in the sort clause. Fucked.
        $this->options = array_reverse( $this->options );
        $this->weights = array_reverse( $this->weights );
    }
    function __invoke()
    {
        $n = rand( 1, $this->total );
        $total = 0;
        reset( $this->weights );
        do {
            list($key, $weight) = each( $this->weights );
            $total += $weight;
        } while ( $total < $n );
        return $this->options[$key];
    }
}