<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver\Exception;

use Bond\DependencyResolver;
use Bond\DependencyResolver\ResolverList;

class DependencyMissingException extends \Exception
{
    public function __construct( $missingDependency, DependencyResolver $resolver, ResolverList $list )
    {
        parent::__construct(
            sprintf(
                "Can't find dependency `%s` for `%s` in passed list %s",
                $missingDependency,
                $resolver->getId(),
                (string) $list
            )
        );
    }
}