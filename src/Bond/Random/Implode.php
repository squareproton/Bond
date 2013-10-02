<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class Implode implements RandomInterface
{
    private $randoms;
    public function __construct( $random1, $random2 )
    {
        $this->randoms = func_get_args();
    }
    public function __invoke()
    {
        $values = array_map(
            function( RandomInterface $random ) {
                return $random();
            },
            $this->randoms
        );
        return implode( func_get_arg(0), $values );
    }
}
