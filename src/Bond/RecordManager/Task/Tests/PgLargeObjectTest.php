<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Tests;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\PgLargeObject\Persist;
use Bond\RecordManager\Task\PgLargeObject\Delete;

use Bond\Entity\Types\PgLargeObject;

use Bond\Pg\Connection;
use Bond\Pg\Result;
use Bond\Sql\Query as PgQuery;
use Bond\Sql\Query;

class PgLargeObjectTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function testInit()
    {
        $task = new Persist($this->entityManager->recordManager);
        $this->assertTrue( $task instanceof Task );
    }

    public function testExecutePersist()
    {

        $fileIn = tempnam( sys_get_temp_dir(), 'PgOidIn' );
        file_put_contents( $fileIn, 'content' );
        $lo = new PgLargeObject( $fileIn );

        $this->assertTrue( $lo->isNew() );
        $this->assertTrue( $lo->isChanged() );
        $this->assertFalse( $lo->isDeleted() );
        $this->assertSame( $lo->getFilePath(), $fileIn );

        $task = new Persist($this->entityManager->recordManager);
        $task->setObject( $lo );
        $this->assertTrue( $task->execute( $this->db ) );

        $this->assertFalse( $lo->isNew() );
        $this->assertFalse( $lo->isChanged() );
        $this->assertFalse( $lo->isDeleted() );
        $this->assertNull( $lo->getFilePath() );

        // the task has been inserted correctly
        $this->assertTrue( is_numeric( $lo->getOid() ) );

        $this->db->query( new PgQuery('BEGIN') );
        $fileOut = tempnam( sys_get_temp_dir(), 'PgOidOut' );
        pg_lo_export( $this->db->resource->get(), $lo->getOid(), $fileOut );
        $this->db->query(new PgQuery('COMMIT'));

        $this->assertFileEquals( $fileIn, $fileOut );
        pg_lo_unlink( $this->db->resource->get(), $lo->getOid() );

    }

    public function testExecuteDelete()
    {

        $fileIn = tempnam( sys_get_temp_dir(), 'PgOidIn' );
        file_put_contents( $fileIn, 'content' );
        $lo = new PgLargeObject( $fileIn );
        $task = new Persist($this->entityManager->recordManager);
        $task->setObject( $lo );
        $this->assertTrue( $task->execute( $this->db ) );

        $noLargeObjects = $this->getNoLargeObjects();

        $task = new Delete($this->entityManager->recordManager);
        $task->setObject( $lo );
        $task->execute( $this->db );

        $this->assertEquals( $this->getNoLargeObjects(), --$noLargeObjects );
        $this->assertFalse( $lo->isNew() );
        $this->assertFalse( $lo->isChanged() );
        $this->assertTrue( $lo->isDeleted() );
        $this->assertNull( $lo->getFilePath() );
        $this->assertNull( $lo->getOid() );

    }

    public function getNoLargeObjects()
    {
        return $this->db->query( new Query( "SELECT count(*) FROM pg_largeobject" ) )->fetch( Result::FETCH_SINGLE );
    }

}