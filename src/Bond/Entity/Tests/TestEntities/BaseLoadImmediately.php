<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Tests\TestEntities;

use Bond\Entity\UnitTest\Base;

class BaseLoadImmediately extends Base
{
    protected static $lateLoad = self::LOAD_DATA_IMMEDIATELY;
}