<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Exception;

class UnknownQueryModifier extends \Exception
{
    public $modifier;
    public function __construct( $modifier )
    {
        $this->modifier = $modifier;
        $this->message("Query modifier modifer `{$modifier}` is unknown.")
    }
}