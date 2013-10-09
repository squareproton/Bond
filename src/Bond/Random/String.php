<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class String implements RandomInterface
{
    public $chars;
    public $length;
    public $min;
    public $max;
    private $last;
    function __construct( $chars, $min, $max = null )
    {
        $this->chars = (string) $chars;
        $this->length = strlen( $chars );
        $this->min = (int) $min;
        if( null === $max ) {
            $this->max = $this->min;
        } else {
            $this->max = (int) $max;
        }
    }
    function __invoke()
    {
        $length = rand( $this->min, $this->max );
        $output = '';
        while( $length-- > 0 ) {
            $output .= $this->chars[rand(0,$this->length-1)];
        }
        return $this->last = $output;
    }
    public function last()
    {
        return $this->last;
    }
}