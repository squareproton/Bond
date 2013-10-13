<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class Coalesce implements RandomInterface
{
    private $randoms;
    private $last;
    public function __construct( $random1, $random2 )
    {
        $this->randoms = func_get_args();
    }
    public function __invoke()
    {
        $value = null;
        reset($this->randoms);
        while( $value === null and list(,$random) = each($this->randoms) ) {
            if( is_object($random) and ( $random instanceof RandomInterface || $random instanceof \Closure) ) {
                $value = $random();
            } else {
                $value = $random;
            }
        }
        return $this->last = $value;
    }
    public function last()
    {
        return $this->last;
    }
}
