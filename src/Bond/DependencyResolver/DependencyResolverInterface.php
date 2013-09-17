<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver;

use Bond\DependencyResolver\ResolverList;

interface DependencyResolverInterface
{
    public function getId();
    public function getDepends();
    public function addDependency( DependencyResolverInterface $dependency );
    public function resolve( ResolverList $resolved, ResolverList $unresolved );
}