<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Tests;

use Bond\Normality\Notify;

use Bond\Pg\Connection;
use Bond\Sql\Raw;
use Bond\Pg\Catalog\Relation;

use Bond\Normality\Tests\NormalityProvider;

class NotifyTest extends NormalityProvider
{

    public function testInsertRecordInReadonly()
    {

        return true;

        $db = Connection::factory('RW');
        $notify = new Notify( 'unitTest' );
        $db->query( $notify );

        $db->listen('bond');

        // insert
        $this->assertSame( $this->getNumberRowsInTable('unit.a2'), 0 );

        $db->query( new Raw("INSERT INTO a2( name ) VALUES ( 'peter1' );") );
        $db->query( new Raw("INSERT INTO a2( name ) VALUES ( 'peter2' );") );

        $this->assertSame( $this->getNumberRowsInTable('unit.a2'), 2 );
        $notifications = $db->getNotifications();

        $this->assertSame( count($notifications), 2 );
        $notification = array_shift( $notifications );
        $notification["payload"] = json_decode( $notification["payload"], true );

        $this->assertSame( $notification['payload']['op'], "INSERT" );
        $this->assertSame( $notification['payload']['table'], "a2" );
        $this->assertSame( $notification['payload']['pk'], array(1) );

        // update
        $db->query( new Raw("UPDATE a2 SET name = 'peter3' WHERE name = 'peter2';") );
        $notifications = $db->getNotifications(true);
        $this->assertSame( count($notifications), 1 );
        $notification = array_shift( $notifications );
        $this->assertSame( $notification['payload']['op'], "UPDATE" );
        $this->assertSame( $notification['payload']['table'], "a2" );
        $this->assertSame( $notification['payload']['pk'], array(2) );

        $db->query( new Raw("UPDATE a2 SET name = 'peter3' WHERE name = 'peter3';") );
        $notifications = $db->getNotifications(true);
        $this->assertSame( count($notifications), 0 );

        // changeing primary key
        $db->query( new Raw("UPDATE a2 SET id = 3000 WHERE id = 1;") );
        $notifications = $db->getNotifications(true);
        $this->assertSame( count($notifications), 2 );
        $this->assertSame( $notifications[0]["payload"]['pk'][0], 1 );
        $this->assertSame( $notifications[1]["payload"]['pk'][0], 3000 );
        $this->assertSame( $notifications[0]["payload"]['op'], 'DELETE' );
        $this->assertSame( $notifications[1]["payload"]['op'], 'INSERT' );

        // delete
        $db->query( new Raw("DELETE FROM a2 WHERE id = 3000;") );
        $notifications = $db->getNotifications(true);
        $this->assertSame( count($notifications), 1 );
        $this->assertSame( $notifications[0]["payload"]['pk'][0], 3000 );
        $this->assertSame( $notifications[0]["payload"]['op'], 'DELETE' );

    }

}