<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\MatchRelation;

use Bond\Pg\Catalog\PgClass;

interface MatchRelationInterface {

    /**
     * Does the passed relation pass our test
     */
    public function __invoke( PgClass $relation );

}