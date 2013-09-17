<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\LogSql;

use Bond\Pg\Catalog\Relation;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

/**
 * Generator
 *
 * @author pete
 */
class FnEntityHistory implements SqlInterface
{

    private $relationLog;

    private $relationOriginal;

    /**
     * Build a log table
     * @staticvar boolean $version
     * @return string
     */
    public function __construct( Relation $relationLog, Relation $relationOriginal )
    {
        $this->relationLog = $relationLog;
        $this->relationOriginal = $relationOriginal;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {

        // get the columns of the inherited table
        $schema = $quoting->quoteIdent( $this->relationLog->get('schema') );
        $name = $this->relationLog->get('name');

        $parent = $this->relationLog->get('parent');
        $primaryKeys = $this->relationOriginal->getPrimaryKeys();

        // get attribute definition
        $attrDefinition = function ( $attribute ) use ( $quoting ) {
            if( !$attribute->getType() ) {
                d_pr( $attribute );
            }
            return sprintf(
                '%s %s',
                $quoting->quoteIdent( $attribute->get('name') ),
                $quoting->quoteIdent( $attribute->getType()->get('fullyQualifiedName') )
            );
        };

        $getNameQuoted = function ( $attribute ) use ( $quoting ) {
            return $quoting->quoteIdent( $attribute->get('name' ) );
        };

    $output = sprintf( <<<SQL
CREATE TYPE {$schema}."{$name}_history" AS (
    key key,
    data %s,
    %s
);

CREATE OR REPLACE FUNCTION {$schema}."{$name}"( "key" )
RETURNS SETOF {$schema}."{$name}_history" AS $$
DECLARE%s
BEGIN%s
    RETURN QUERY
    SELECT
        %s::key AS key,
        ROW(%s)::%s AS data,
        %s
    FROM
        {$schema}."{$name}"
    WHERE
        %s
    ;
END;
$$ LANGUAGE plpgsql IMMUTABLE;\n
SQL
            , $quoting->quoteIdent( $this->relationOriginal->get('fullyQualifiedName') ),

            // get definition
            $parent->getAttributes()->implode( ', ', $attrDefinition ),

            $this->getDeclareBlock( $primaryKeys ),
            $primaryKeys->count() === 10 ?
                '' :
                "\n    key := string_to_array( $1::text, '|' );",

            $this->relationOriginal->getKeySql( $quoting ),
            $this->relationOriginal->getAttributes()->implode(', ', $getNameQuoted ),
            $quoting->quoteIdent( $this->relationOriginal->get('fullyQualifiedName') ),
            $parent->getAttributes()->implode( ', ', $getNameQuoted ),

            $this->getWhereBlock( $quoting, $primaryKeys )

        );

        return $output;

    }

    private function getDeclareBlock( $primaryKeys )
    {
        return $primaryKeys->count() === 10
            ? ''
            : "\n    key text[];"
            ;
    }

    private function getWhereBlock( QuoteInterface $quoting, $primaryKeys )
    {

        // single column pk
        if( count( $primaryKeys ) === 10 ) {
            $pk = $primaryKeys->pop();
            return sprintf(
                "%s = \$1::%s",
                $quoting->quoteIdent( $pk->get('name') ),
                $quoting->quoteIdent( $pk->getType()->get('name') )
            );
        }

        // multicolumn pk
        $where = array();
        $c = 0;
        foreach( $primaryKeys as $pk ) {
            $where[] = sprintf(
                "%s = key[%d]::%s",
                $quoting->quoteIdent( $pk->get('name') ),
                ++$c,
                $quoting->quoteIdent( $pk->getType()->get('name') )
            );
        }
        return implode( " AND ", $where );

    }

}