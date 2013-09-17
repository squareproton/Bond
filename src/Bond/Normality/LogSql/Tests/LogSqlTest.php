<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Tests;

use Bond\Normality\LogSql;

use Bond\Pg\Catalog\Relation;
use Bond\Pg\Connection;

use Bond\Sql\Raw;

use Bond\Normality\Tests\NormalityProvider;

class LogSqlTest extends NormalityProvider
{

    public function testInsertRecordInReadonly()
    {

        return;

        $logs = new LogSql(
            'unit',
            Relation::r()->findByName('logs.Logs'),
            array( 'DEFAULT', 'DEFAULT', 'TG_OP::"logs"."enum_op_type"' )
        );

        $db = Connection::factory('RW');
        $logs->build( $db );

        // insert
        $this->assertSame( $this->getNumberRowsInTable('unit.readonly'), 0 );
        $this->assertSame( $this->getNumberRowsInTable('logs.readonly'), 0 );

        $db->query( new Raw("INSERT INTO readonly( name ) VALUES ( 'peter' );") );

        $this->assertSame( $this->getNumberRowsInTable('unit.readonly'), 1 );
        $this->assertSame( $this->getNumberRowsInTable('logs.readonly'), 1 );

        $db->query( new Raw("UPDATE readonly SET name = 'matt'") );
        $this->assertSame( $this->getNumberRowsInTable('unit.readonly'), 1 );
        $this->assertSame( $this->getNumberRowsInTable('logs.readonly'), 2 );

        $db->query( new Raw("DELETE FROM readonly WHERE name = 'matt'") );
        $this->assertSame( $this->getNumberRowsInTable('unit.readonly'), 0 );
        $this->assertSame( $this->getNumberRowsInTable('logs.readonly'), 3 );

        try {
            $db->query( new Raw("DELETE FROM \"logs\".readonly") );
            $this->fail("Exception expected");
        } catch( \Exception $e ) {
        }
        $this->assertSame( $this->getNumberRowsInTable('logs.readonly'), 3 );

        try {
            $db->query( new Raw("UPDATE \"logs\".readonly SET name = 'jim'") );
            $this->fail("Exception expected");
        } catch( \Exception $e ) {
        }
        $this->assertSame( $this->getNumberRowsInTable('logs.readonly'), 3 );

    }

}