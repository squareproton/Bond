<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver\Exception;

use Bond\DependencyResolver\ResolverList;
use Bond\DependencyResolver\DependencyResolverInterface;

class CircularDependencyException extends \Exception
{
    public function __construct( ResolverList $list, DependencyResolverInterface $resolver )
    {
        $circularReference = [];
        $chainStarted = false;
        foreach( $list as $chainLink ) {
            if( $chainLink === $resolver ) {
                $chainStarted = true;
            }
            if( $chainStarted ) {
                $circularReference[] = $chainLink->getId();
            }
        }
        $circularReference[] = $resolver->getId();

        $circularReference = implode( " -> ", $circularReference );
        parent::__construct( "Circular reference detected {$circularReference}" );
    }
}