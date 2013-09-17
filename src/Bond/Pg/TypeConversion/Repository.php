<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\TypeConversion;

use Bond\Repository as EntityRepository;

class Repository extends TypeConversion
{

    private $repository;
    private $entityKeys;

    public function __construct( $type )
    {
        parent::__construct($type);
        $entityName = substr($type, 1);
        $this->repository = EntityRepository::init($entityName);
        $this->entityKeys = $this->repository;
    }

    /*
     * Parse a prostgres string representation of a entity into a properly key'd assoc array
     * @param string Postgres array (string)
     * @return array
     */
    public function __invoke( $input )
    {

        // Do we want null safety? Does this even work?
        if( $string === null ) {
            return null;
        }

        $records = str_getcsv( trim( $string, '{}'), ',', '"', '\\' );

        $output = array();
        foreach( $records as $row ) {

            // empty set
            if( !empty( $row ) and $row !== 'NULL'  ) {

                $row = trim( $row, '()' );
                $row = stripslashes( $row );

                // explode
                $data = str_getcsv( $row, ',','"', '\\' );
                $data = array_map( 'stripslashes', $data );

                if( $keys ) {
                    $output[] = array_combine( $keys, $data );
                } else {
                    $output[] = $data;
                }

            }

        }

        return $output;

        /*
        # in container?
        return $repo->initByDatas(
            \Bond\Pg\Result::postgresEntityArrayStringToArray( $value, $keys )
        );
        */

    }

}