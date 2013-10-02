<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class Nullify implements RandomInterface
{
    public $random;
    public $chance;
    public function __construct( RandomInterface $random, $chance = 0.5 )
    {
        $this->random = $random;
        $this->chance = $chance;
    }
    function __invoke()
    {
        if( (rand()/getrandmax()) <= $this->chance ) {
            return null;
        }
        return $this->random->__invoke();
    }
}