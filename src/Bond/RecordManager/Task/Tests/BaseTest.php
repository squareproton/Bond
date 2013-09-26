<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Tests;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Base;

class BaseTest extends \Bond\Tests\EntityManagerProvider
{

    public function testInit()
    {

        $task = new Base($this->entityManager->recordManager);

        $this->assertTrue( $task instanceof Base );
        $this->assertTrue( $task instanceof Task );
        $this->assertTrue( $task->execute( $this->entityManager->db ) );

    }

}