<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Repository;

use Bond\Pg\Catalog\Sequence as Entity;

use Bond\Sql\Query;

/**
 * Description of Repository
 * @author pete
 */
class Sequence extends Relation
{

    /**
     * @inheritDoc
     */
    public function findByTypes( array $type = array('S') )
    {
        return parent::findByTypes( $type );
    }

}