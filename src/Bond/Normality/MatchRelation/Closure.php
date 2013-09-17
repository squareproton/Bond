<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\MatchRelation;

use Bond\Normality\MatchRelation\MatchRelationInterface;
use Bond\Pg\Catalog\PgClass;

class Closure implements MatchRelationInterface
{

    private $closure;

    public function __construct( Callable $closure )
    {
        $this->closure = $closure;
    }

    public function __invoke( PgClass $relation )
    {
        return call_user_func( $this->closure, $relation );
    }

}