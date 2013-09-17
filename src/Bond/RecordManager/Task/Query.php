<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task;

use Bond\RecordManager\Task;

use Bond\Pg;
use Bond\Pg\Connection;
use Bond\Sql\SqlInterface;

class Query extends Task
{

    /**
     * {@inheritDoc}
     */
    public function execute( Pg $pg, $simulate = false )
    {
        $pg->query( $this->object );
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function isCompatible( $object, &$error = null )
    {
        if( !( $object instanceof SqlInterface ) ) {
            $error = "object is not a instance of Bond\Sql\SqlInterface";
            return false;
        }
        return true;
    }

}