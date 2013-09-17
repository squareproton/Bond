<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Tests;

use Bond\Entity\Types\StockState;
use Bond\MainBundle\Entity\ContactUser;
use Bond\MainBundle\Entity\Location;
use Bond\Normality\UnitTest\Entity\State;

use Bond\Pg\Connection;
use Bond\Pg\Result;
use Bond\Sql\Query;
use Bond\Sql\Raw;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Normality\Persist;
use Bond\RecordManager\Task\Normality\Delete;

use Bond\Normality\Tests\NormalityProvider;

class StockStateTest extends NormalityProvider
{

    public function testNothing()
    {
    }

    /*

    public function testGet()
    {

        $rows = $this->populate();

        $repo = State::r();
        $states = $repo->findAll();

        $this->assertSame(
            $states->count(),
            (int) Connection::factory('RW')->query( new Query('SELECT count(*) FROM state') )->fetch( Result::FETCH_SINGLE )
        );

    }

    public function testPutNullState()
    {

    $db = Connection::factory('RW');

        $state = new State(
            array(
                'stockState' => null
            )
        );

        $this->assertNull( $state->get('stockState') );

        $task = new Persist();
        $task->setObject( $state );
        $task->execute( $db );

        $data = $db->query( new Query('SELECT "stockState" FROM state') )->fetch( Result::FETCH_SINGLE );
        $this->assertNull( $data );

    }

    public function testStateBitfield()
    {

    $db = Connection::factory('RW');

        $state = new State(
            array(
                'stockState' => new StockState(
                    array(
                        'properties' => 3
                    )
                 )
            )
        );

        $this->assertSame( 3, $state->get('stockState')->properties );

        $task = new Persist();
        $task->setObject( $state );
        $task->execute( $db );

        $data = $db->query( new Query('SELECT ("stockState").properties FROM state') )->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
        $this->assertSame( $data, 3 );

    }

    public function populate()
    {

        $query = new Raw( <<<SQL
            INSERT INTO
                "state" ( "stockState" )
            VALUES
                ( ROW( 1, null, null, null ) ),
                ( ROW( 2, null, null, 0 ) ),
                ( ROW( 3, null, null, 3 ) ),
                ( NULL )
            ;
SQL
        );

        $result = Connection::factory('RW')->query( $query );
        return $result->affectedRows();

    }

    /**/

}