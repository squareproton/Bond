<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog;

class PgSequence extends PgClass
{

//    /**
//     * Execute some sequence SQL and return the result
//     * @param SqlInterface $query
//     * @return mixed
//     */
//    private function runSequenceFunction( SqlInterface $query )
//    {
//        $db = $this->r()->db->query( $query );
//        return $result->fetch( Result::FETCH_SINGLE );
//    }
//
//    /**
//     * Get the current sequence value
//     * @return int|null
//     */
//    public function getCurval()
//    {
//
//        try {
//            return $this->runSequenceFunction(
//                new Query(
//                    "SELECT currval( %oid:oid% );",
//                    array( 'oid' => $this->get('oid') )
//                )
//            );
//
//        // State55000 - http://archives.postgresql.org/pgsql-sql/2008-04/msg00218.php
//        } catch( State55000 $e ) {
//            return null;
//        }
//
//    }

}