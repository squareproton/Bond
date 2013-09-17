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
class FnTableAtState implements SqlInterface
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
        $relationLog = $quoting->quoteIdent( $this->relationLog->get('fullyQualifiedName') );
        $relationOriginal = $quoting->quoteIdent( $this->relationOriginal->get('fullyQualifiedName') );
        $schema = $quoting->quoteIdent( $this->relationLog->get('schema') );
        $name = $this->relationLog->get('name');

        // build output columns
        $columnsLog = $this->relationLog->getAttributes();
        $columnsOriginal = $this->relationOriginal->getAttributes();

        $columns = array();
        foreach( $columnsOriginal as $columnOriginal ) {

            $column = $quoting->quoteIdent( $columnOriginal->get('name') );

            $typeLog = $columnsLog->findByName( $columnOriginal->get('name') )->shift()->getType();
            $typeOriginal = $columnOriginal->getType();

            if( $typeLog !== $typeOriginal ) {
                $column .= '::' . $quoting->quoteIdent( $typeOriginal->get('fullyQualifiedName') );
            }

            $columns[] = $column;

        }
        $columns = implode( ', ', $columns );

        // get a attribute name quoted
        $getNameQuoted = function ( $attribute ) use ( $quoting ) {
            return $quoting->quoteIdent( $attribute->get('name' ) );
        };

        $output = <<<SQL
CREATE FUNCTION {$schema}."{$name}"( "logId" INT )
RETURNS SETOF {$relationOriginal} AS \$\$
    SELECT
        {$columns}
    FROM
        ( SELECT * FROM {$relationLog} WHERE "logId" <= $1 AND op <> 'DELETE' ) _
    INNER JOIN
        ( SELECT MAX("logId") AS "logId" FROM {$relationLog} WHERE "logId" <= $1 GROUP BY {$this->relationOriginal->getPrimaryKeys()->implode(', ', $getNameQuoted)} ) __
    USING
        ("logId")
    ;
\$\$ LANGUAGE SQL IMMUTABLE;
SQL;

        return $output;

    }

}