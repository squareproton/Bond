<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di\Exception;

class NoResourcesDefinedException extends \Exception
{
    public $reflClass;
    public function __construct( \ReflectionClass $reflClass )
    {
        $this->reflClass = $reflClass;
        $this->message = <<<TEXT
Using Bond\Di\DiTestCase in {$this->reflClass->getName()} without defining any resources (probably) isn't right.
You probably should just be inheriting off \PHPUnit_Framework_Testcase.
TEXT
;

    }
}