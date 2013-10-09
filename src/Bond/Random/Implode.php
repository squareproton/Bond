<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class Implode implements RandomInterface
{
    private $randoms;
    private $last;
    public function __construct( $random1, $random2 )
    {
        $this->randoms = func_get_args();
    }
    public function __invoke()
    {
        $separator = func_num_args() ? func_get_arg(0) : '';
        $values = array_map(
            function( $random ) {
                if( is_object($random) and ( $random instanceof RandomInterface || $random instanceof \Closure) ) {
                    return $random();
                } else {
                    return (string) $random;
                }
            },
            $this->randoms
        );
        return $this->last = implode( $separator, $values );
    }
    public function last()
    {
        return $this->last;
    }
}
