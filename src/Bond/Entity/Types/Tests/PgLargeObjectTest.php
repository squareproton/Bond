<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use Bond\RecordManager\Task;

use Bond\Entity\Types\FileInterface;
use Bond\Entity\Types\PgLargeObject;
use Bond\Entity\Types\Oid;

use Bond\Pg\Connection;
use Bond\Sql\Query as PgQuery;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PgOidTest extends \Bond\Tests\EntityManagerProvider
{

    static $filesToUnlink = array();

    public function testConstructWithOid()
    {

        $oid = new Oid( 12345678, $this->connectionFactory->get('RW') );
        $lo = new PgLargeObject( $oid );

        $this->assertSame( $lo->getOid(), $oid );
        $this->assertFalse( $lo->isNew(), false );
        $this->assertFalse( $lo->isChanged(), false );

    }

    public function testConstructWithFilenameDoesntExist()
    {
        $this->setExpectedException("RuntimeException");
        $lo = new PgLargeObject( './spanner' );
    }

    public function testConstructWithFilenameDoesExist()
    {

        $file = $this->makeAndFillFile();

        $lo = new PgLargeObject( $file );
        $this->assertSame( $lo->getFilePath(), $file );

    }

    public function testConstructWithBadArgument()
    {

        $this->setExpectedException("InvalidArgumentException");
        $lo = new PgLargeObject( false );

    }

//    public function testConstructWithUploadedFile()
//    {
//
//        $file = $this->makeAndFillFile();
//        $uploadedFile = new UploadedFile( $file, 'random.txt', 'text/plain', filesize($file) );
//        $lo = new PgLargeObject( $uploadedFile );
//        $this->assertSame( $lo->getFilePath(), $file ) ;
//
//    }

    public function testGetDataFromFile()
    {
        $file = $this->makeAndFillFile();
        $type = new PgLargeObject( $file );

        $this->assertSame( $type->data(), file_get_contents( $file ) );
    }

    public function testGetDataFromDatabase()
    {
        $oid = $this->makePgLO( $data );
        $type = new PgLargeObject( $oid );

        $this->assertSame( $type->data(), $data );
    }

    public function testExportFromFile()
    {

        $file = $this->makeAndFillFile();
        $type = new PgLargeObject( $file );
        $data = file_get_contents( $file );

        $file = $this->makeTmpFileName();
        $type->export( $file );

        $this->assertSame( file_get_contents( $file ), $data );

    }

    public function testExportFromDatabase()
    {
        $oid = $this->makePgLO( $data );
        $type = new PgLargeObject( $oid );

        $file = $this->makeTmpFileName();
        $type->export( $file );

        $this->assertSame( file_get_contents( $file ), $data );

    }

    public function testStreamFromFilesystem()
    {

        $file = $this->makeAndFillFile();
        $type = new PgLargeObject( $file );
        $data = file_get_contents( $file );

        ob_start();
        $type->stream();
        $this->assertSame( ob_get_clean(), $data );

    }

    public function testStreamFromDatabase()
    {

        $oid = $this->makePgLO( $data );
        $type = new PgLargeObject( $oid );

        ob_start();
        $type->stream();
        $this->assertSame( ob_get_clean(), $data );

    }

    public function testMd5Filesystem()
    {
        $contents = "spanner";
        $file = $this->makeAndFillFile( $contents );
        $type = new PgLargeObject( $file );

        $this->assertSame( $type->md5(), md5( $contents ) );
    }

    public function testMd5Database()
    {
        $contents = "spanner";
        $oid = $this->makePgLO( $contents );
        $type = new PgLargeObject( $oid );

        $this->assertSame( $type->md5(), md5( $contents ) );

    }

    public function makeTmpFileName()
    {
        $filename = sprintf(
            "%s/%s",
            sys_get_temp_dir(),
            md5( microtime() . rand(1, 100000 ) )
        );
        self::$filesToUnlink[] = $filename;
        return $filename;
    }

    public function makeAndFillFile( $data = null )
    {

        $file = tempnam( sys_get_temp_dir(), 'test' );
        $filesToUnlink[] = $file;

        if( isset( $data ) ) {
            file_put_contents( $file, $data );
            return $file;
        }

        exec(
            sprintf(
                'dd if=/dev/urandom of=%s bs=1K count=%d 2>/dev/null',
                $file,
                mt_rand( 5, 15 )
            )
        );

        return $file;

    }

    public function makePgLO( &$data = null )
    {

        $pg = $this->connectionFactory->get('RW');
        $pgResource = $pg->resource->get();

        if( !isset( $data ) ) {
            $data = file_get_contents( $this->makeAndFillFile() );
        }

        pg_query( $pgResource, "BEGIN" );

        $oid = pg_lo_create( $pgResource );
        $handle = pg_lo_open( $pgResource, $oid, 'w' );
        pg_lo_write( $handle, $data );

        pg_query( $pgResource, "COMMIT" );

        return new Oid( $oid, $pg );

    }

}