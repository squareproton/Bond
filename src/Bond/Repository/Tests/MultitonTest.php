<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Repository\Tests;

use Bond\EntityManager;
use Bond\Entity\EventEmitter;

use Bond\Pg\Tests\PgProvider;
use Bond\Repository;

class MultitonTest extends PgProvider
{

    public function setup()
    {

        parent::setup();

        $this->entityManager = new EntityManager(
            $this->connectionFactory->get('RW'),
            [],
            new EventEmitter()
        );

        $this->entityManager->register( Entity::class, Multiton::class );

    }

    public function testConstant()
    {
        $repo = $this->entityManager->getRepository('Entity');
        $this->assertSame( $repo->constantGet('PERSISTED'), Repository::PERSISTED );

    }

    public function testMake()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $obj1 = $repo->make();
        $obj2 = $repo->make();

        $this->assertNotSame( $obj1, $obj2 );

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 0 );
        $this->assertSame( $repo->cacheSize( Repository::UNPERSISTED ), 2 );
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 2 );

    }

    public function testFind()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id = 123;
        $obj1 = $repo->find( $id );
        $obj2 = $repo->find( $id );

        $this->assertSame( $obj1, $obj2 );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

    }

    public function testFindNoCache()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id = 123;
        $obj1 = $repo->find( $id );
        $obj2 = $repo->find( $id, null, false );

        $this->assertTrue( $repo->isCached( $obj1 ) );
        $this->assertFalse( $repo->isCached( $obj2 ) );
        $this->assertNotSame( $obj1, $obj2 );

    }

    public function testFindLateLoad()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $obj1 = $repo->find( 1 );
        $this->assertFalse( $obj1->isLoaded() );

        $obj2 = $repo->find( 2, true );
        $this->assertTrue( $obj2->isLoaded() );

    }

    public function testAttach()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $new = new Entity();
        $old = new Entity( array('id'=>123, 'name' => 'name' ) );

        $this->assertFalse( $repo->isAttached( $new ) );
        $this->assertFalse( $repo->isAttached( $old ) );

        $this->assertEquals( $old->keyget($old), 123 );

        $this->assertTrue( $repo->attach( $new, $finalRestingPlaceNew ) );
        $this->assertTrue( $repo->attach( $old, $finalRestingPlaceOld ) );

        $this->assertTrue( $repo->isAttached( $new ) );
        $this->assertTrue( $repo->isAttached( $old ) );

        $this->assertSame( $finalRestingPlaceNew, Repository::UNPERSISTED );
        $this->assertSame( $finalRestingPlaceOld, Repository::PERSISTED );

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED), 1 );
        $this->assertSame( $repo->cacheSize( Repository::UNPERSISTED), 1 );

    }

    public function testDetach()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $new = $repo->make();
        $old = $repo->find( 123 );

        $this->assertSame( $repo->cacheSize( Repository::ALL ), 2 );

        $this->assertTrue( $repo->detach( $new, $detachedFromNew ) );
        $this->assertSame( $detachedFromNew, Repository::UNPERSISTED );
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 1 );

        $this->assertTrue( $repo->detach( $old, $detachedFromOld ) );
        $this->assertSame( $detachedFromOld, Repository::PERSISTED );
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 0 );

        // detaching a object twice does nothing
        $this->assertFalse( $repo->detach( $old, $detachedFromOld ) );
        $this->assertNull( $detachedFromOld );

    }

    public function testGarbageCollectionRemoveByObject()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id = 123;
        $obj1 = $repo->find( $id );

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

        $removed = $repo->garbageCollect( $obj1 );

        $this->assertSame( $removed, 1 );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 0 );

    }

    public function testGarbageCollectionAttemptedRemoveByObject()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id = 123;
        $obj1 = $repo->find( $id );

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

        $obj1->set( 'name', 'spanner' );
        $this->assertTrue( $obj1->isChanged(), true );

        $removed = $repo->garbageCollect( $obj1 );
        $this->assertSame( $removed, 0 );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

    }

    public function testGarbageCollectionRemoveByKey()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id = 123;
        $obj1 = $repo->find( $id );

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

        $removed = $repo->garbageCollect( $id );

        $this->assertSame( $removed, 1 );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 0 );

    }

    public function testGarbageCollectionAttemptedRemoveByKey()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id = 123;
        $obj1 = $repo->find( $id );

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

        $obj1->set( 'name', 'spanner' );
        $this->assertTrue( $obj1->isChanged(), true );

        $removed = $repo->garbageCollect( $id );
        $this->assertSame( $removed, 0 );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

    }

    public function testGarbageCollectionMaxInstancesTripped()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $max = $repo->instancesMaxAllowed;

        $instances = array();
        for( $i=0; $i<$max; $i++) {
            $instances[] = $repo->find( $i );
        }
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), $max );

        // add one more, remove first from cache
        $instances[++$i] = $repo->find( $i );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), $max );
        $this->assertFalse( $repo->isCached( $instances[0]) );

        // add one more, remove second from cache
        $instances[0] = $repo->find( 0 );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), $max );
        $this->assertTrue( $repo->isCached( $instances[0] ) );
        $this->assertFalse( $repo->isCached( $instances[1] ) );

    }

    public function testCheckGarbagecollectionDoesntRemoveChangedItems()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $max = $repo->instancesMaxAllowed;

        $instances = array();
        for( $i=0; $i < ( $max * 2) ; $i++) {

            $instances[$i] = $repo->find( $i );
            $instances[$i]->set( 'name', "NAME-$i" );

        }

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), $max * 2);

        // try removing by object
        $repo->garbageCollect( $instances[1] );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), $max * 2);

        // try removing by key
        $repo->garbageCollect( 1 );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), $max * 2);

    }

    public function testReKey()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id1 = 1;
        $id2 = 2;

        $obj1 = $repo->find( $id1 );

        // change this object's id
        $obj1->__construct( array( 'id' => $id2 ) );
        $this->assertSame( $obj1, $repo->find( $id1 ) );

        $this->assertTrue( $repo->rekey( $obj1 ) );
        $this->assertSame( $obj1, $repo->find( $id2 ) );
        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

    }

    public function testSetDirectRepositoryRekeying()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id1 = 123;
        $id2 = 234;

        $obj1 = $repo->find( $id1 );
        $this->assertSame( $repo->find($id1), $obj1 );

        $this->assertSame( $obj1->setDirect('id', $id2 ), 1 );
        $this->assertSame( $repo->find($id2), $obj1 );

        $this->assertSame( $repo->cacheSize( Repository::PERSISTED ), 1 );

    }

    public function testSetDirectWithCollision()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $id1 = 123;
        $id2 = 234;

        $obj1 = $repo->find( $id1 );
        $obj2 = $repo->find( $id2 );

        $this->setExpectedException( "RuntimeException" );
        $obj1->setDirect( 'id', $id2 );

    }

    public function testInitNullBug()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $obj1 = $repo->find( 123 );

        $this->assertTrue( $repo->isCached( $obj1 ) );
        $this->assertNull( $repo->find(null) );

    }

    public function testIsNew()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $new = $repo->make();
        $old = $repo->find(1);

        $this->assertTrue( $repo->isNew( $new ) );
        $this->assertFalse( $repo->isNew( $old ) );

    }

    public function testIsNewSetStateNewToOldFailure1()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $new = $repo->make();
        $repo->isNew( $new, false );

        $this->assertFalse( $repo->isAttached( $new ) );

    }

    public function testIsNewSetStateNewToOldFailure2()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $new = $repo->make();
        $old = $repo->find(1);
        $new->setDirect(array('id'=>1));

        $this->assertTrue( $repo->isCached( $new, Repository::UNPERSISTED ) );
        $this->assertTrue( $repo->isCached( $old, Repository::PERSISTED ) );

        $this->setExpectedException("Bond\\Repository\\Exception\\MultitonKeyCollisionException");
        $repo->isNew( $new, false );

    }

    public function testIsNewSetStateNewToOldSuccess()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $new = $repo->make();
        $new->setDirect(array('id'=>1));
        $this->assertTrue( $repo->isCached( $new, Repository::UNPERSISTED ) );

        $repo->isNew( $new, false );
        $this->assertTrue( $repo->isCached( $new, Repository::PERSISTED ) );
        $this->assertFalse( $repo->isCached( $new, Repository::UNPERSISTED ) );

    }

    public function testIsNewSetStateOldToNew()
    {
        $repo = $this->entityManager->getRepository('Entity');
        $old = $repo->find(1);

        $this->assertFalse( $repo->isNew( $old ) );

        $this->assertTrue( $repo->isNew( $old, true ) );
        $this->assertSame( count( $repo->persistedGet() ), 0 );
        $this->assertSame( count( $repo->unpersistedGet() ), 1 );

    }

    public function testNotFound()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $old = $repo->find('notfound', true);
        $this->assertNull( $old );

    }

    public function testCacheInvalidateAll()
    {

        $repo = $this->entityManager->getRepository('Entity');
        $old1 = $repo->find(1);
        $new = $repo->make();

        $repo->cacheInvalidate();
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 0 );

        $old2 = $repo->find(1);
        $this->assertNotSame( $old1, $old2 );

    }

    public function testCacheInvalidatePersisted()
    {

        $repo = $this->entityManager->getRepository('Entity');
        $old1 = $repo->find(1);
        $new = $repo->make();

        $repo->cacheInvalidate( Repository::PERSISTED );
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 1 );

        $old2 = $repo->find(1);
        $this->assertNotSame( $old1, $old2 );

    }

    public function testCacheInvalidateUnpersisted()
    {

        $repo = $this->entityManager->getRepository('Entity');
        $old1 = $repo->find(1);
        $new = $repo->make();

        $repo->cacheInvalidate( Repository::UNPERSISTED );
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 1 );
        $this->assertSame( $repo->cacheSize( Repository::UNPERSISTED ), 0 );

        $old2 = $repo->find(1);
        $this->assertSame( $old1, $old2 );

    }

    public function testInitByData()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $data = array(
            'id' => 1,
            'name' => 'name',
        );

        $this->assertNull( $repo->initByData( null ) );
        $this->assertNull( $repo->initByData( array() ) );

        $obj = $repo->initByData( $data );

        $this->assertTrue( $obj instanceof Entity );
        $this->assertSame( $repo->find(1), $obj );
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 1 );

    }

    public function testInitByDataAlreadyExists()
    {

        $repo = $this->entityManager->getRepository('Entity');

        $obj = $repo->find(1);

        $data = array(
            'id' => 1,
            'name' => 'name',
        );

        $this->assertSame( $repo->initByData( $data ), $obj );
        $this->assertSame( $repo->cacheSize( Repository::ALL ), 1 );

    }

/**/

}