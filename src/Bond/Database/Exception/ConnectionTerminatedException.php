<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database\Exception;

use Bond\Pg\Resource;

class ConnectionTerminatedException extends \Exception
{
    public $resource;
    public function __construct( Resource $resource )
    {
        $this->resource = $resource;
        $this->message = sprintf(
            "Resource has been terminated. It may no longer be used."
        );
    }
}