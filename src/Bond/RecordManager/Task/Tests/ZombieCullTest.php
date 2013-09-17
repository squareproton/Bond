<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Tests;

use Bond\Container;

use Bond\Normality\UnitTest\Entity\A11;
use Bond\Normality\UnitTest\Entity\A1;
use Bond\Normality\UnitTest\Entity\A1linkA4;
use Bond\Normality\UnitTest\Entity\A4;

use Bond\Pg\Result;
use Bond\Sql\Query;

use Bond\RecordManager\Task\Normality;
use Bond\RecordManager\Task\Normality\Persist;
use Bond\RecordManager\Task\NormalityCollection\Persist as PersistCollection;

class ZombieCullTest extends \Bond\Normality\Tests\NormalityProvider
{

/*
    public function testIsZombie()
    {

        $a11r = $this->entityManager->getRepository('A11');
        $a1r = $this->entityManager->getRepository('A1');
        $a4r = $this->entityManager->getRepository('A4');
        $a11linka4r = $this->entityManager->getRepository('A1linkA4');

        $a11 = $a11r->make();
        $this->assertTrue( $a11->isZombie() );

        $a11->set('a1_id', $a1r->make() );
        $this->assertFalse( $a11->isZombie() );

        $link = $a11linka4r->make();
        $this->assertTrue( $link->isZombie() );

        $link->set('a1_id', $a1r->make() );
        $this->assertTrue( $link->isZombie() );
        $link->set('a4_id', $a4r->make() );
        $this->assertFalse( $link->isZombie() );

    }
    */

    public function testZombieCull()
    {

        $a1 = $this->entityManager->getRepository('A1')->make();
        $a4 = $this->entityManager->getRepository('A4')->make(['type'=>'one']);
        $link = $this->entityManager->getRepository('A1linkA4')->make();
        $link['a1_id'] = $a1;
        $link['a4_id'] = $a4;

        $db = $this->entityManager->recordManager->db;
//        $db->debug->start();

        $linkTask = new Persist($this->entityManager->recordManager);
        $linkTask->setObject( $link );

        $this->assertFalse( $link->isZombie() );
        $this->assertTrue( $linkTask->execute($db) );

        $this->assertSame(
            $db->query( new Query( 'SELECT count(*) FROM "a1_link_a4";' ) )->fetch( Result::FETCH_SINGLE ),
            "1"
        );

        // zombie link
        $link['a1_id'] = null;

        $this->assertTrue( $link->isZombie() );

        $this->assertTrue( $linkTask->execute( $this->entityManager->recordManager->db ) );
        $this->assertSame(
            $db->query( new Query( 'SELECT count(*) FROM "a1_link_a4";' ) )->fetch( Result::FETCH_SINGLE ),
            "0"
        );

    }

/*
    public function testZombieCullContainerTest()
    {

        $a11s = array();
        $container = new Container();

        $n = 0;
        while( $n++ < 2 ) {

            $a1 = $this->entityManager->getRepository('A1')->make();
            $a11 = $this->entityManager->getRepository('A11')->make();
            $a11['a1_id'] = $a1;

            $a11s[] = $a11;
            $container->add( $a11 );

        }

        $db = $this->entityManager->recordManager->db;

        $a11Task = new PersistCollection($this->entityManager->recordManager);
        $a11Task->setObject( $container );
        $this->assertTrue( $a11Task->execute( $db ) );

        $this->assertSame(
            $db->query( new Query( 'SELECT count(*) FROM "a11";' ) )->fetch( Result::FETCH_SINGLE ),
            "2"
        );

        $container->map(function($e){
            $e->set('a1_id', null);
        });

        $this->assertTrue( $a11Task->execute( $db) );
        $this->assertSame(
            $this->db->query( new Query( 'SELECT count(*) FROM "a11";' ) )->fetch( Result::FETCH_SINGLE ),
            "0"
        );

    }
    */

}

