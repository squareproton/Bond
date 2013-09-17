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

class IdCollisionException extends \Exception
{
    public function __construct(ResolverList $list, DependencyResolverInterface $resolver )
    {
        parent::__construct(
            sprintf(
                'The resolverlist object already contains a member with id `%s`',
                $resolver->getId()
            )
        );
    }
}