<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di\Exception;

class ClassNotAConfigurableException extends \Exception
{
    public $class;
    public function __construct ($class)
    {
        $this->class = $class;
        $this->message = "{$class} isn't a something that works with the Bond\Di\Configurator. Check it has a __invoke() method.";
    }
}