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

class Regex implements MatchRelationInterface {

    private $regex;

    public function __construct( $regex )
    {
        $this->regex = $regex;
    }

    /**
     * Does the passed relation pass our test
     */
    public function __invoke( PgClass $relation )
    {
        $tags = $relation->getTags();

        $match = isset( $tags['match'] ) ? $tags['match'] : $relation->name;
        return (bool) preg_match( $this->regex, $match );
    }

}