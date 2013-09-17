<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Tests\TestEntities;

use Bond\Entity\Base as EB;

class Base extends EB
{

    /**
     * The property used for lateLoading
     * @var string
     */
    protected static $lateLoadProperty = 'id';

    /**
     * Properties which comprise this entities 'key'
     * @var array
     */
    protected static $keyProperties = ['id'];

    /**
     * Data array
     * @var array
     */
    protected $data = array(
        'id' => null,
        'name' => null,
    );

}