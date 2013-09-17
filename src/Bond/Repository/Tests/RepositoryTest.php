<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Repository\Test;

use Bond\Container;
use Bond\Container\FindFilterComponentFactory;
use Bond\Container\FindFilterComponent\PropertyAccessScalarEquality;
use Bond\Container\FindFilterComponent\PropertyAccessContainerEquality;

use Bond\Entity\PropertyMapperEntityData;
use Bond\Entity\PropertyMapperEntityInitial;

use Bond\Normality\UnitTest\Entity\A1;
// use Bond\Normality\UnitTest\Entity\A11;
// use Bond\Normality\UnitTest\Entity\A2;
// use Bond\Normality\UnitTest\Entity\A3;
// use Bond\Normality\UnitTest\Entity\A4;
// use Bond\Normality\UnitTest\Entity\A1linkA4;
// use Bond\Normality\UnitTest\Entity\View;
// use Bond\Normality\UnitTest\Entity\MView;

use Bond\Entity\Base;
use Bond\Entity\DataType;

use Bond\Repository;
use Bond\RecordManager;

use Bond\Set\Integer;
use Bond\Set\Text;

use Bond\Pg\Catalog;
use Bond\Pg\Connection;
use Bond\Pg\Result;

use Bond\Sql\Raw;
use Bond\Sql\Query;

use Bond\Normality\Tests\NormalityProvider;

class RepositoryTest extends NormalityProvider
{

    public function populateA1()
    {

        $query = new Raw( <<<SQL
            INSERT INTO a2( name ) VALUES( 'a2' );

            INSERT INTO a1( int4, string, foreign_key )
            SELECT
                i, 'string-'||i::text,
                currval('"unit"."a2_id_seq"'::regclass)
            FROM
                generate_series( 1, 100 ) as g( i );
SQL
        );

        return $this->db->query($query);

#        $rm = RecordManager::init();
#        $a1r = $this->entityManager->getRepository('A1');
#        $a2r = $this->entityManager->getRepository('A2');
#        $entityContainer =  new Container();
#        foreach( range(1,100) as $n ) {
#            $a1 = $a1r->make();
#            $a1->set('int4', $n );
#            $a1->set('string', "string-{$n}" );
#            $a1->set('A2', $a2 );
#            $rm->persist( $a1 );
#            $entityContainer->add( $a1 );
#        }
#        $rm->flush();
#        return $entityContainer;

    }

    public function testPopulateA1()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        $this->populateA1();

        $this->assertSame( $this->getNumRowsInTable('a1'), 100 );
        $this->assertSame( $this->getNumRowsInTable('a1'), 100 );

        $this->assertSame( count( $a1r->findUnpersisted() ), 0 );
        $this->assertSame( count( $a1r->findPersisted() ), 100 );

        $this->assertSame( count( $a2r->findPersisted() ), 1 );

    }

    public function populateA3()
    {

        $query = new Raw( <<<SQL
            INSERT INTO a3 ( pk1, pk2 )
            SELECT generate_series(1,100), generate_series(1,100) * 2;
SQL
        );

        return $this->db->query($query);

    }

    public function testPopulateA3()
    {
        $this->populateA3();
        $this->assertSame( $this->getNumRowsInTable('a3'), 100 );
    }

    public function testRepositoryConstantsMatchEntityConstants()
    {
        $this->assertSame( Base::DATA, Base::DATA );
        $this->assertSame( Base::INITIAL, Base::INITIAL );
    }

    public function testFindByMultitonFilter()
    {

        $a2r = $this->entityManager->getRepository('A2');

        $vars = array('one','two');

        foreach( $vars as $var ) {
            $$var = $a2r->make();
            $$var->set('name', $var);
        }

        foreach( $vars as $var ) {

            // find all
            $found = $a2r->findAllByName( $var );
            $this->assertSame( count($found), 1 );
            $this->assertTrue( $found->contains($$var) );

            // find one
            $found = $a2r->findOneByName( $var );
            $this->assertSame( $found, $$var );

            // find Persisted
            $found = $a2r->findUnpersistedByName( $var );
            $this->assertSame( count($found), 1 );
            $this->assertTrue( $found->contains($$var) );

            // find Unpersisted
            $found = $a2r->findPersistedByName( $var );
            $this->assertSame( count($found), 0 );

            // find changed
            $found = $a2r->findChangedByName( $var );
            $this->assertSame( count($found), 1 );
            $this->assertTrue( $found->contains($$var) );

        }

    }

    public function testInit()
    {

        $r1 = $this->entityManager->getRepository('A1');
        $r2 = $this->entityManager->getRepository('A1');

        $this->assertSame( $r1, $r2 );

    }

    public function testFindByMultiton()
    {

         $a2r = $this->entityManager->getRepository('A2');
         $a2r->make();

         $a2s = $a2r->findAll();
         $this->assertSame( count( $a2s ), 1 );

    }

    public function testFindAllByContainerPersisted()
    {

        $this->populateA1();
        $a1r = $this->entityManager->getRepository('A1');
        $mapper = new PropertyMapperEntityData('foreignKey');

        $ffc = new PropertyAccessContainerEquality(
            $mapper,
            null,
            $this->entityManager->getRepository('A2')->findAll()
        );

        $a1s = $a1r->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            [ 'foreign_key' => $ffc ],
            Repository::PERSISTED
        );
        $this->assertTrue( $a1s->isSameAs( $a1r->findAll() ) );

        $ffc->value = new Container();
        $a1s = $a1r->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            [ 'foreign_key' => $ffc ],
            Repository::PERSISTED
        );
        $this->assertSame( $a1s->count(), 0 );

    }

    public function testFindAllByContainerUnpersisted()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');
        $mapper = new PropertyMapperEntityData('foreign_key');

        foreach( range(1,100) as $n ) {
            $a2 = $a2r->make( array('name'=>$n));
            $a1 = $a1r->make(
                array(
                    'int4' => $n,
                    'string' => "string-{$n}",
                    'foreign_key' => $a2,
                )
            );
        }

        $a2s = $a2r->findAll()->randomGet(5);

        $filter = new PropertyAccessContainerEquality(
            $mapper,
            null,
            $a2s
        );

        $a1s = $a1r->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            [ 'foreign_key' => $filter ],
            Repository::UNPERSISTED
        );

        $this->assertSame(
            implode(',', $a2s->pluck('name') ),
            implode(',', $a1s->pluck('int4') )
        );

        $filter->value = new Container();

        // empty containers cause no errors
        $a1s = $a1r->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            [ 'foreign_key' => $filter ],
            Repository::UNPERSISTED
        );
        $this->assertSame( $a1s->count(), 0 );

    }

    public function testFindAllPersistedChangedUnchanged()
    {

        $this->populateA1();
        $a1r = $this->entityManager->getRepository('A1');

        $one = $a1r->find(1);
        $one->set('string','one');

        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    array(),
                    Repository::PERSISTED + Repository::CHANGED
                )->isSameAs( new Container( $one ) )
        );

        $this->assertFalse(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    array(),
                    Repository::PERSISTED + Repository::UNCHANGED
                )->contains( $one )
        );

    }

    public function testFindAllUnPersistedChangedUnchanged()
    {

        $this->populateA1();
        $a1r = $this->entityManager->getRepository('A1');

        $unpersisted = array();;
        foreach( range( 500, 700 ) as $n ) {
            $entity = $a1r->make();
            $entity->set('string', $n);
            $unpersisted[] = $entity;
        }
        $unpersisted = new Container( $unpersisted );

        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    array(),
                    Repository::UNPERSISTED
                )->isSameAs( $unpersisted )
        );

        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    array(),
                    Repository::UNPERSISTED + Repository::UNCHANGED
                )->isSameAs( new Container() )
        );

        $unpersisted->each(function($e){ $e->checksumReset(); });

        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    array(),
                    Repository::UNPERSISTED + Repository::UNCHANGED
                )->isSameAs( $unpersisted )
        );

    }

    public function testFindByFilterComponentsInitialVsData()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        $a2_one = $a2r->make();
        $a2_two = $a2r->make();

        $one = $a1r->make( array( 'foreign_key' => $a2_one ) );
        $two = $a1r->make( array( 'foreign_key' => $a2_two ) );

        $factoryData = new FindFilterComponentFactory([PropertyMapperEntityData::class ]);
        $factoryInitial = new FindFilterComponentFactory([PropertyMapperEntityData::class, PropertyMapperEntityInitial::class]);

        // find one -> data
        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    [ 'foreign_key' => $factoryData->get('foreign_key', null, $a2_one ) ],
                    Base::DATA
                )->isSameAs( new Container( $one ) )
        );

        // find one -> initial
        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    [ 'foreign_key' => $factoryInitial->get('foreign_key', null, $a2_one ) ],
                    Base::INITIAL
                )->isSameAs( new Container( $one ) )
        );

        // clear one
        $one->startDataInitialStore();
        $one->set('foreign_key', null );

        // find one -> data
        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    [ 'foreign_key' => $factoryData->get('foreign_key', null, $a2_one ) ],
                    Base::DATA
                )->isSameAs( new Container() )
        );

        // find one -> initial
        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    [ 'foreign_key' => $factoryInitial->get('foreign_key', null, $a2_one ) ],
                    Base::INITIAL
                )->isSameAs( new Container( $one ) )
        );

        $two->set('foreign_key', $a2_one );
        $this->assertTrue(
            $a1r->findByFilterComponents(
                    FindFilterComponentFactory::FIND_ALL,
                    [ 'foreign_key' => $factoryInitial->get('foreign_key', null, $a2_one ) ],
                    Base::INITIAL
                )->isSameAs( new Container( $one, $two ) )
        );

    }

    public function testFindAllPersistedUnpersistedChanged()
    {

        $repo = $this->entityManager->getRepository( new A1() );

        // generate and persist a load of A1s
        $a1s_saved = $repo->findAll();

        // generate and __DONT__ persist a load of A1s
        $a1s_new = new Container();
        foreach( $a1s_saved as $_ ) {
            $a1s_new->add( $repo->make() );
        }

        $a1s_unpersisted = $repo->findUnpersisted();
        $a1s_persisted = $repo->findPersisted();
        $a1s_changed = $repo->findChanged();
        $a1s_all = $repo->findAll();

        $this->assertTrue( $a1s_saved->isSameAs( $a1s_persisted ) );
        $this->assertTrue( $a1s_new->isSameAs( $a1s_unpersisted ) );
        $this->assertTrue( $a1s_new->isSameAs( $a1s_changed ) );
        $this->assertTrue( $a1s_all->contains( $a1s_persisted, $a1s_unpersisted ) );
        $this->assertSame( count($a1s_all), count($a1s_saved) + count($a1s_new) );

    }

    public function testFindAllBySet()
    {

        $this->populateA1();

        $repo = $this->entityManager->getRepository('A1');

        $byInt = $repo->findAllByInt4(100);
        $bySet = $repo->findAllByInt4( new Integer(100) );
        $this->assertTrue( $byInt->isSameAs( $bySet ) );

        $byInt = $repo->findAll();
        $bySet = $repo->findAllByInt4( new Integer('-') );
        $this->assertTrue( $byInt->isSameAs( $bySet ) );

        $byInt = new Container( $repo->find(1), $repo->find(2), $repo->find(3), $repo->find(50), $repo->find(99), $repo->find(100) );
        $bySet = $repo->findAllByInt4( new Integer('-3,50,99-') );
        $this->assertTrue( $byInt->isSameAs( $bySet ) );

        $bySet = $repo->findAllByCc( new Text(null) );
        $this->assertTrue( $repo->findAll()->isSameAs( $bySet ) );

        $byInt = new Container( $repo->find(50), $repo->find(51), $repo->find(52), $repo->find(53) );
        $bySet = $repo->findAllByString( new Text('string\\-50-string\\-53') );

    }

    public function testGetLinksUseCase()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a4r = $this->entityManager->getRepository('A4');
        $a1linka4r = $this->entityManager->getRepository('A1linkA4');

        $a1 = $a1r->make();
        $a4 = $a4r->make();
        $a1_link_a4 = $a1linka4r->make();

        $a1_link_a4->set('A1', $a1 );
        $a1_link_a4->set('A4', $a4 );

    }

    public function testReferencesGetByEntity()
    {

        $a2r = $this->entityManager->getRepository('A2');
        $a1r = $this->entityManager->getRepository('A1');

        $a2 = $a2r->make();
        $a1r->make()->set('foreign_key', $a2 );
        $a1r->make()->set('foreign_key', $a2 );
        $a1r->make()->set('foreign_key', $a2 );

        $this->assertTrue(
            $a2r->referencesGet( $a2, 'A1.foreign_key' )
                ->isSameAs( $a1r->referencedBy( $a2 ) )
        );

    }

    public function testReferencesGetByContainer()
    {

        $a2r = $this->entityManager->getRepository('A2');
        $a1r = $this->entityManager->getRepository('A1');

        $a2_1 = $a2r->make();
        $a1r->make()->set('foreign_key', $a2_1 );

        $a2_2 = $a2r->make();
        $a1r->make()->set('foreign_key', $a2_2 );

        $this->assertTrue(
            $a2r->referencesGet( new Container( $a2_1, $a2_2 ), 'A1.foreign_key' )
                ->isSameAs( $a1r->referencedBy( new Container( $a2_1, $a2_2 ) ) )
        );

    }

    // references where there is a 1 to 1 relationship, we'd normally expect a entity but we've passed a container
    public function testReferencesGetByContainerOneToOneRelationshipButPassedContainer()
    {

        $a11s = new Container(
            $this->entityManager->getRepository('A11')->make(
                array(
                    'A1' => $a1_1 = $this->entityManager->getRepository('A1')->make(),
                )
            ),
            $this->entityManager->getRepository('A11')->make(
                array(
                    'A1' => $a1_2 = $this->entityManager->getRepository('A1')->make(),
                )
            )
        );

        $a11s_viarepo = $this->entityManager->getRepository('A1')->referencesGet(
            new Container( $a1_1, $a1_2 ),
            'A11.a1_id'
        );

    }

    public function testReferencedBy()
    {

        $a2r = $this->entityManager->getRepository('A2');
        $a1r = $this->entityManager->getRepository('A1');

        $a2 = $a2r->make();
        $a1r->make()->set('A2', $a2 );
        $a1r->make()->set('A2', $a2 );
        $a1r->make()->set('A2', $a2 );

        $entities = $a1r->referencedBy( $a2 );

        $this->assertSame( count( $entities ), 3 );

    }

    public function testNormalityA2Repo()
    {

        $a2r = $this->entityManager->getRepository('A2');
        $a1r = $this->entityManager->getRepository('A1');

        $a2 = $a2r->make();

        $a1r->make()->set('A2', $a2 );
        $a1r->make()->set('A2', $a2 );
        $a1r->make()->set('A2', $a2 );

        $this->assertSame( count( $a2->get('A1s') ), 3 );

    }

    public function testInitAndMultiton()
    {

        $repo = $this->entityManager->getRepository('A1');

        $this->assertTrue( $repo instanceof Repository );
        $this->assertSame( $repo, $this->entityManager->getRepository('A1') );
        $this->assertSame( $repo, $this->entityManager->getRepository( $repo->make() ) );

        // none of the following should throw a exception
        $repo->findAll();
        $repo->findAllById( null );
        $repo->findOneById( null );
        $repo->findOneByIdAndString( null, null);
        $repo->findOneByIdAndStringOrInt4(  null, null, null );
        $repo->findOneByStringAndId( null, null );

    }

    public function testMake()
    {
        $repo = $this->entityManager->getRepository('A1');
        $a1 = $repo->make();
        $this->assertTrue( $a1 instanceof A1 );
        $this->assertTrue( $a1->isLoaded() );
    }

    public function testMakeWithData()
    {
        $repo = $this->entityManager->getRepository('A1');
        $a1 = $repo->make(
            array(
                'id' => 123,
                'int4' => 4,
            )
        );
        $this->assertTrue( $a1->isNew() );
        $this->assertTrue( $a1->isLoaded() );
        $this->assertSame( $a1['id'], null ); // Unsettable property. This is expected to fail.
        $this->assertSame( $a1['int4'], 4 );
    }

    public function testFindBy()
    {

        $this->populateA1();

        $repo = $this->entityManager->getRepository('A1');

        $a1s = $repo->findAll();
        $a2s = $repo->findAllByInt4('66');

        $this->assertTrue( $a1s instanceof Container );
        $this->assertTrue( $a2s instanceof Container );

        $this->assertSame( count($a1s), 100 );
        $this->assertSame( count($a2s), 1 );

    }

    public function testFindBySet()
    {

        $this->populateA1();

        $repo = $this->entityManager->getRepository('A1');

        $this->assertSame( $repo->findBySet( new Integer('51-') )->count(), 50 );
        $this->assertSame( $repo->findBySet( new Integer('50-59') )->count(), 10 );
        $this->assertSame( $repo->findBySet( new Integer('1,2,3') )->count(), 3 );
        $this->assertSame( $repo->findBySet( new Integer(1,2,3,4) )->count(), 4 );
        $this->assertSame( $repo->findBySet( new Integer('-') )->count(), 100 );
        $this->assertSame( $repo->findBySet( new Integer(null) )->count(), 0 );

    }

    public function testFindBySetNull()
    {

        $repo = $this->entityManager->getRepository('A1');
        $repo->make();

        $this->assertSame( $repo->findBySet( new Integer(null) )->count(), 1 );

    }

    public function testFindBySetPersistedViaMultiton()
    {

        $repo = $this->entityManager->getRepository('A1');

        $a1s = array();
        foreach( range(1,100) as $n ) {

            $a1 = new A1(
                array(
                    'id' => $n
                )
            );
            $repo->attach( $a1 );

            $a1s[$n] = $a1;

        }

        $keys = array(1,2);
        $container = new Container( array_intersect_key( $a1s, array_flip( $keys ) ) );
        $this->assertTrue(
            $container->isSameAs(
                $repo->findBySet( new Integer($keys) )
            )
        );

        $keys = range(10,50);
        $container = new Container( array_intersect_key( $a1s, array_flip( $keys ) ) );
        $this->assertTrue(
            $container->isSameAs(
                $repo->findBySet( new Integer($keys) )
            )
        );

    }

    public function testFindBySetUnersistedViaMultiton()
    {

        $repo = $this->entityManager->getRepository('A1');

        $a1s = array();
        foreach( range(1,100) as $n ) {

            $a1 = $repo->make();
            $a1->setDirect(
                array(
                    'id' => $n
                )
            );
            $a1s[$n] = $a1;

        }

        $keys = array(5,9,15,56,99);
        $container = new Container( array_intersect_key( $a1s, array_flip( $keys ) ) );
        $this->assertTrue(
            $container->isSameAs(
                $repo->findBySet( new Integer($keys) )
            )
        );

    }

    public function testLinksGetViaRepo()
    {

        $repo = $this->entityManager->getRepository('A1');

        $a1 = $repo->make();
        $a4 = $this->entityManager->getRepository('A4')->make();

        $a1linka4 = $this->entityManager->getRepository('A1linkA4')->make();
        $a1linka4->set('a1_id', $a1 );
        $a1linka4->set('a4_id', $a4 );

        $a4sViaRepo = $repo->linksGet( $a1, 'A4', null, null, false );

        $this->assertTrue( $a4sViaRepo->contains($a1linka4) );
        $this->assertSame( count( $a4sViaRepo ), 1 );

        $a4sViaEntity = $a1->get('A1linkA4s');

        // identical containers
        $this->assertTrue( $a4sViaEntity->isSameAs( $a4sViaRepo ) );

        // via containers
        $a4sViaContainer = $repo->linksGet( new Container( $a1 ), 'A4', null, null, false );

        $this->assertTrue( $a4sViaEntity->isSameAs( $a4sViaContainer ) );

    }

    public function testData()
    {

        $this->populateA1();
        $this->populateA3();

        $a1r = $this->entityManager->getRepository('A1');
        $a3r = $this->entityManager->getRepository('A3');

        $db = $this->db;

        // single column pk
        $a1_50 = $db->query( new Query( "SELECT * FROM a1 WHERE id = 50" ) )->fetch( Result::FETCH_SINGLE );
        $this->assertEquals( $a1_50, $a1r->data(50) );
        $this->assertEquals( $a1_50, $a1r->data("50") );
        $this->assertEquals( $a1_50, $a1r->data(array('id'=>"50")) );

        // multi column pk
        $a3_1020 = $db->query( new Query( "SELECT * FROM a3 WHERE ( pk1, pk2 ) IN ( ( 10, 20 ) )" ) )->fetch( Result::FETCH_SINGLE );
        $this->assertEquals( $a3_1020, $a3r->data("10|20") );
        $this->assertEquals( $a3_1020, $a3r->data(array('pk1'=> "10", "pk2" => "20")) );

    }

    public function testDataNonPrimaryKeyArrays()
    {

        $this->populateA1();

        $a1r = $this->entityManager->getRepository('A1');
        $db = $this->db;

        // single column pk
        $a1_50 = $db->query( new Query( "SELECT * FROM a1 WHERE id = 50" ) )->fetch( Result::FETCH_SINGLE );
        $this->assertEquals( $a1_50, $a1r->data(array('int4'=>"50")) );
        $this->assertEquals( $a1_50, $a1r->data(array('string'=>"string-50")) );

    }

    public function testDataRecordNotFound()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $this->assertEquals( $a1r->data("not a real key"), array() );

    }

    public function testDataExceptionNoKey()
    {
        $repo = $this->entityManager->getRepository('A1');
        try {
            $repo->data();
            $this->fail("At least one argument is required");
        } catch( \Exception $e ) {
            $this->assertTrue(true);
        }
    }

    public function testDataExceptionKeyWithNoKeyArray()
    {
        $repo = $this->entityManager->getRepository('A1');
        $this->setExpectedException("InvalidArgumentException");
        $repo->data(array());
    }

    public function testDataExceptionKeyWithWrongComponents()
    {
        $repo = $this->entityManager->getRepository('A1');
        $this->setExpectedException("Bond\\Entity\\Exception\\BadKeyException");
        $repo->data('12|12');
    }

    public function testDataExceptionKeyWithBadKey()
    {
        $repo = $this->entityManager->getRepository('A1');
        $this->setExpectedException("InvalidArgumentException");
        $repo->data(array('badkey'=>''));
    }

    public function testFind()
    {

        $this->populateA1();

        $repo = $this->entityManager->getRepository('A1');

        $db = $this->db;
        $a1_66 = $repo->find(66);

        $this->assertSame( $a1_66['string'], 'string-66' );

    }

    public function testView()
    {

        // unable to make a new view element
        $repo = $this->entityManager->getRepository('View');
        $all = $repo->findAll();

        $one = $all->pop();
        $this->assertEquals( $one->get('test'), 1 );

        $this->setExpectedException("Bond\\Repository\\Exception\\EntityNotMakeableException");
        $new = $repo->make();

    }

    public function testDataTypesGet()
    {

        $repo = $this->entityManager->getRepository('A1');

        $keys = array_keys(
            $repo->dataTypesGet()
        );

        $catalog = new Catalog( $this->entityManager->db );

        $columns = $catalog->pgClasses->findByName('unit.a1')->pop()->getAttributes();

        $this->assertEquals( $keys, $columns->pluck('name') );

        $this->assertEquals(
            array_keys( $repo->dataTypesGet( DataType::PRIMARY_KEYS ) ),
            array('id')
        );

        $this->assertEquals(
            array_keys( $repo->dataTypesGet( DataType::FORM_CHOICE_TEXT ) ),
            array('string')
        );

        $this->assertEquals(
            array_keys( $repo->dataTypesGet( array('int4', 'b') ) ),
            array('int4','b')
        );

        $this->assertEquals(
            array_keys( $repo->dataTypesGet('int4', 'b') ),
            array('int4','b')
        );

        $this->assertEquals(
            array_keys( $repo->dataTypesGet( 'b' ) ),
            array('b')
        );

        $this->setExpectedException('InvalidArgumentException');
        $repo->dataTypesGet( 'columnReallyDoesNotExist' );

    }

    public function testFindByfilterComponentsDontLookIntheDatabaseItWontHelpYou()
    {

        $this->populateA1();

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        $db = $this->entityManager->db;
        $numQuerys = $db->numQuerys;

        # Verify the unit tests are using the same db connection as the repos.
        $a2r->findAll();
        $this->assertSame( ++$numQuerys, $db->numQuerys );

        $factory = new FindFilterComponentFactory( [PropertyMapperEntityData::class] );

        # The following shouldn't hit the database -  empty container
        $a1s = $a1r->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            [ 'foreign_key' => $factory->get(
                    'foreign_key',
                    null,
                    new Container()
                )
            ],
            Repository::ALL
        );
        $this->assertSame( $numQuerys, $db->numQuerys );

        return;

        # container with two non persisted entities
        $a1s = $a1r->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            array(
                'foreign_key' => FindFilterComponent::factory(
                    'foreign_key',
                    null,
                    new Container( $a2r->make(), $a2r->make() )
                )
            ),
            Repository::ALL
        );
        $this->assertSame( $numQuerys, $db->numQuerys );

        # entity
        $a1s = $a1r->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            array(
                'foreign_key' => FindFilterComponent::factory(
                    'foreign_key',
                    null,
                    $a2r->make()
                )
            ),
            Repository::ALL
        );
        $this->assertSame( $numQuerys, $db->numQuerys );

    }

}