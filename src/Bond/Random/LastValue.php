<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class LastValue implements RandomInterface
{
    private $random;
    private $last;
    function __construct( RandomInterface $random )
    {
        $this->random = $random;
    }
    function __invoke()
    {
        return $this->last = $this->random->last();
    }
    public function last()
    {
        return $this->last;
    }
}