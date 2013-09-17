<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

use Bond\Entity\Types\Oid;

use Bond\Pg\Result;
use Bond\RecordManager\ChainSavingInterface;

use Bond\Sql\Query;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PgLargeObject implements SqlInterface, FileInterface, ChainSavingInterface
{

    /**
     * PgSQL OID
     * @var Bond\Entity\Types\Oid
     */
    private $oid;

    /**
     * Path to file on the server.
     * @var mixed
     */
    private $filePath;

    /**
     * Database where we think this object is
     */
    private $pg;

    /**
     * Class constructor. Accepts instanceof UploadedFile, numeric OID
     * or a path to the file on the server.
     * @param mixed $value
     * @return PgOid
     */
    public function __construct( $value )
    {

        switch( true ){

            case $value instanceof UploadedFile:

                $this->filePath = $value->getPathname();
                break;

            case $value instanceof Oid:

                $this->oid = $value;
                break;

            case is_string( $value ):

                if( !file_exists( $value ) ) {
                    throw new \RuntimeException( sprintf( "File '%s' does not exist", $value ) );
                }

                if( !is_readable( $value ) ) {
                    throw new \RuntimeException( sprintf( "File '%s' is not readable", $value ) );
                }

                $this->filePath = $value;
                break;

            default:

                throw new \InvalidArgumentException(
                    sprintf(
                        "Don't know how to handle argument of type '%s'",
                        gettype( $value )
                    )
                );
                break;
        }
    }

    /**
     * Add a object to the chain task array if it is changed or new
     * Required for ChainSavingInterface
     *
     * @param array $task The existing array of tasks
     * @param string $key The string under which to add the object
     */
    public function chainSaving( array &$tasks, $key )
    {
        if( $this->isNew() or $this->isChanged() ) {
            $tasks[$key] = $this;
        }
    }

    /**
     * Get the filepath passed to the constructor if
     * instantiated with an UploadedFile or filepath
     * @param void
     * @return mixed
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Get the OID for the current resource.
     * @param void
     * @return mixed
     */
    public function getOid()
    {
        return $this->oid;
    }

    /**
     * Set the OID for the current resource.
     * @param mixed $oid
     * @return void
     */
    public function markPersisted( $oid )
    {
        $this->oid = $oid;
        $this->filePath = null;
    }

    /**
     * Set object as deleted
     * @return void
     */
    public function markDeleted()
    {
        $this->oid = null;
        $this->filePath = null;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {
        return (string) $this->oid;
    }

    /**
     * Is this object deleted
     * @return bool
     */
    public function isDeleted()
    {
        return !isset( $this->oid ) and !isset( $this->filePath );
    }

    /**
     * Is this object changed in any way
     * @return bool
     */
    public function isChanged()
    {
        return isset( $this->filePath ) && !isset( $this->oid );
    }

    /**
     * Is this is a new PgOid object
     * @return bool
     */
    public function isNew()
    {
        return isset( $this->filePath ) && !isset( $this->oid );
    }

    /**
     * Return the MD5 hash of the data
     * @return mixed
     */
    public function md5()
    {

        if( isset( $this->filePath ) ) {

            return md5_file( $this->filePath );

        } elseif( $this->oid ) {

            $query = new Query(
                "SELECT common.md5( %oid:oid|cast% );",
                array(
                    'oid' => $this->oid
                )
            );

            // execute the query and return the result
            return $this->oid->pg->query( $query )->fetch( Result::FETCH_SINGLE );

        }

        throw \LogicException("Shouldn't ever get tripped");

    }

    /**
     * Get the data stored in the large object resource.
     * @param void
     * @return mixed
     */
    public function data()
    {

        if( isset( $this->filePath ) ) {

            return file_get_contents( $this->filePath );

        } elseif( $this->oid ) {

            $pg = $this->oid->pg;
            $pg->query( new Query( 'BEGIN' ) );

            $handle = pg_lo_open( $pg->resource->get(), $this->oid->oid, 'r' );

            $result = '';
            while( ( $data = pg_lo_read( $handle ) ) ){
                $result .= $data;
            }

            pg_lo_close( $handle );

            $pg->query( new Query( 'COMMIT' ) );

            return $result;

        }

        throw \LogicException("Shouldn't ever get tripped");

    }

    /**
     * Export the data stored in the large object resource to the specified file.
     * @param mixed $destination
     * @return boolean.
     */
    public function export( $destination, $overwriteIfExists = false )
    {

        if( !is_string( $destination ) ){
            throw new \RuntimeException(sprintf(
                'Expected $destination to be a string, got type: %s',
                gettype($destination)
            ));
        }

        if( file_exists( $destination ) and !$overwriteIfExists ) {
            throw new \RuntimeException( sprintf( "File '%s' already exists.", $destination ) );
        }

        if( !is_writable( dirname( $destination ) ) ){
            throw new \RuntimeException( sprintf( "File '%s' is not writable.", $destination ) );
        }

        if( isset( $this->filePath ) ) {
            return copy( $this->filePath, $destination );
        }

        $pg = $this->oid->pg;;

        $pg->query( new Query( 'BEGIN' ) );

        $result = pg_lo_export( $pg->resource->get(), $this->oid->oid, $destination );

        $pg->query( new Query( 'COMMIT' ) );

        return $result;

    }

    /**
     * Output the data stored in the large object resource to stdout.
     * @param void
     * @return void
     */
    public function stream()
    {

        // filesystem
        if( isset( $this->filePath ) ) {

            return readfile( $this->filePath );

        } elseif ( $this->oid ) {

            $pg = $this->oid->pg;

            $pg->query( new Query( 'BEGIN' ) );

            $handle = pg_lo_open( $pg->resource->get(), $this->oid->oid, 'r' );

            pg_lo_read_all( $handle );
            pg_lo_close( $handle );

            $pg->query( new Query( 'COMMIT' ) );

            return null;

        }

        throw \LogicException("Shouldn't ever get tripped");

    }

}