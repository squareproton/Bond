<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\Tests;

class FindFilterComponentFactory extends \Bond\Container\FindFilterComponentFactory
{

    /**
     * {@inheritDoc}
     */
    public function getReflClassFromComparisonValue( $value )
    {
        return new \ReflectionClass(
            __NAMESPACE__ . '\\FindFilterComponent\\Vanilla'
        );
    }

}