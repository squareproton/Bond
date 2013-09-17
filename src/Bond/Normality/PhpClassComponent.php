<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\Format;
use Bond\MagicGetter;
use Bond\Exception\BadTypeException;

abstract class PhpClassComponent
{

    use MagicGetter;

    protected $name;
    protected $content;

    public function __construct( $name, $value )
    {
        $this->name = $name;

        if( is_string( $value ) ) {
            $this->content = new Format($value);
        } elseif ($value instanceof Format) {
            $this->content = $value;
        } else {
            throw new BadTypeException($value, "String or Bond\Format");
        }
    }
}