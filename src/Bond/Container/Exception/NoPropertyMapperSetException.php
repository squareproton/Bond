<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\Exception;

class NoPropertyMapperSetException extends \Exception
{
    public function __construct()
    {
        $this->message = <<<TEXT
No propertyMapper set for this container and you've called a method on the container which requires access to them.
TEXT
;
    }
}
