<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Tests;

use Bond\Pg;
use Bond\Pg\Resource;
use Bond\ServerSettings;
use Bond\Sql\Query;

function getResourceInstances()
{
    $reflResourceInstances = (new \ReflectionClass('Bond\Pg\Resource'))->getProperty('instances');
    $reflResourceInstances->setAccessible(true);
    return $reflResourceInstances->getValue();
}

class ResourceTest extends PgProvider
{

    public function testInstantiation()
    {

        $settingsDb = $this->connectionSettingsRW;

        $resourceObj = new Resource( $settingsDb );

        $this->assertTrue( is_resource( $resourceObj->get() ) );
        $this->assertTrue( is_resource( $resourceObj->get(true) ) );
        $this->assertSame( $settingsDb, $resourceObj->connectionSettings );

        $this->assertTrue( $resourceObj->isAlive() );
        $this->assertFalse( $resourceObj->isTerminated() );

        $resourceObj->terminate();

    }

    public function testInstantiationMultitonStore()
    {

        $settingsDb = $this->connectionSettingsRW;

        $r1 = new Resource( $settingsDb );
        $this->assertSame( count( getResourceInstances() ), 1 );

        $r2 = new Resource( $settingsDb );
        $this->assertSame( count( getResourceInstances() ), 2 );

        $r2->terminate();
        $this->assertSame( count( getResourceInstances() ), 1 );

        $r1->terminate();
        $this->assertSame( count( getResourceInstances() ), 0 );

    }

    public function testTermination()
    {

        $settingsDb = $this->connectionSettingsRW;

        $r1 = new Resource( $settingsDb );
        $r1->terminate();

        $this->assertTrue( $r1->isTerminated(false) );
        $this->setExpectedException('Bond\Database\Exception\ConnectionTerminatedException');
        $r1->isTerminated();

    }

    public function testReset()
    {

        $settingsDb = $this->connectionSettingsRW;
        $o1 = new Resource($settingsDb);
        $r1 = $o1->get();

        $this->assertSame( $r1, $o1->get() );
        $this->assertSame( PGSQL_CONNECTION_OK, pg_connection_status($r1) );

        $o1->reset();

        $r2 = $o1->get();
        $this->assertNotSame( $r1, $r2);

        try {
            pg_connection_status($r1);
            $this->fail("shouldn't be valid connection resource");
        } catch( \Exception $e ) {
        }

        $this->assertSame( PGSQL_CONNECTION_OK, pg_connection_status($r2) );
        $o1->terminate();

    }

    public function testIsAlive()
    {

        $settingsDb = $this->connectionSettingsRW;
        $o1 = new Resource($settingsDb);

        $this->assertTrue( $o1->isAlive() );

        // mess with the connection and trash it externally. This simulates a dropped connection
        $r1 = $o1->get();
        pg_close( $r1 );

        $this->assertFalse( $o1->isAlive() );
        $o1->terminate();

    }

    public function testGet()
    {

        $settingsDb = $this->connectionSettingsRW;
        $o1 = new Resource($settingsDb);
        $r1 = $o1->get();

        $this->assertSame( $r1, $o1->get() );

        // trash the connection
        pg_close( $r1 );
        $this->assertTrue( $r1 === $o1->get() );

        $r2 = $o1->get(true);
        $this->assertFalse( $r1 === $r2 );
        $o1->terminate();

    }

    public function testEnsure()
    {

        $settingsDb = $this->connectionSettingsRW;

        $r1 = new Resource( $settingsDb );
        $r2 = new Resource( $settingsDb );

        $this->assertSame( Resource::ensure(), 0 );

        pg_close( $r1->get() );
        $this->assertFalse( $r1->isAlive() );
        $this->assertSame( Resource::ensure(), 1 );
        $this->assertTrue( $r1->isAlive() );
        $this->assertSame( Resource::ensure(), 0 );

        pg_close( $r1->get() );
        $r1->terminate();
        $this->assertSame( Resource::ensure(), 0 );
        $r2->terminate();

    }

    public function testSerialization()
    {

        $settingsDb = $this->connectionSettingsRW;
        $r1 = new Resource($settingsDb);

        $s1 = serialize( $r1 );
        $r2 = unserialize( $s1 );

        $this->assertSame( PGSQL_CONNECTION_OK, pg_connection_status($r2->get()) );

        $r1->terminate( $r1 );
        $r2->terminate( $r2 );

    }

}