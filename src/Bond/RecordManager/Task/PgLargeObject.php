<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task;

use Bond\RecordManager\Task;
use Bond\Entity\Types\PgLargeObject as PgLargeObjectType;

class PgLargeObject extends Task
{

    public static function isCompatible( $object, &$error = null )
    {
        return $object instanceof PgLargeObjectType;
    }

}