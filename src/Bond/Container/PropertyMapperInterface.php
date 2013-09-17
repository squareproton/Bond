<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container;

interface PropertyMapperInterface
{
    public function __construct( $property );
    public function get( $obj );
    public function set( $obj, $value );
    public function compatibilityCheck( $obj );
}