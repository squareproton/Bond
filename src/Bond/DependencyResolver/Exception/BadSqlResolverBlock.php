<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver\Exception;

use Bond\DependencyResolver\Sql;

class BadSqlResolverBlock extends \Exception
{
    public $resolver;
    public $resolverBlock;
    public function __construct( Sql $resolver, $resolverBlock )
    {
        $this->resolver = $resolver;
        $this->resolverBlock = $resolverBlock;
        $this->message = sprintf(
            "Dependency `%s` has a malformed resolver block.\n --- BEGIN RESOLVER BLOCK ---\n%s\n--- END RESOLVER BLOCK ---\n",
            $resolver->getId(),
            $resolverBlock
        );
    }
}