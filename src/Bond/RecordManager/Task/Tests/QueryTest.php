<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Tests;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Query;

use Bond\Sql\Raw;

class QueryTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function testInit()
    {

        $task = new Query($this->entityManager->recordManager);
        $task->setObject( new Raw("not a valid sql query") );
        $this->assertTrue( $task instanceof Query );
        $this->assertTrue( $task instanceof Task );

        $this->setExpectedException('Bond\\Pg\\Exception\\QueryException');
        $this->assertTrue( $task->execute( $this->db ) );

    }

    public function testSuccess()
    {

        $task = new Query($this->entityManager->recordManager);

        $this->assertTrue( $task instanceof Query );
        $this->assertTrue( $task instanceof Task );

        $task->setObject( new Raw( "SELECT 1") );
        $this->assertTrue( $task->execute( $this->db ) );

    }

}