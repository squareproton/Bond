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

class Base extends Task
{

    /**
     * {@inheritDoc}
     */
    public function execute( Pg $pg, $simulate = false )
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function isCompatible( $object, &$error )
    {
        return true;
    }

}