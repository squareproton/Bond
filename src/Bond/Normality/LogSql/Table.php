<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\LogSql;

use Bond\Pg\Catalog\Attribute;
use Bond\Pg\Catalog\Relation;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Bond\Normality\Generator;

/**
 * Generator
 *
 * @author pete
 */
class Table implements SqlInterface
{

    /**
     * @var inherits
     */
    private $inherits = null;

    /**
     * @var Array of inherited values
     */
    private $inheritedValues = array();

    /**
     * Recast some datatype long term storage purposes
     */
    private static $recast = array(
        'citext' => 'text',
    );

    /**
     * Build a log table
     * @staticvar boolean $version
     * @return string
     */
    public function __construct( Relation $relation, Relation $inherits, $inheritedValues )
    {
        $this->relation = $relation;
        $this->inherits = $inherits;
        $this->inheritedValues = $inheritedValues;
    }

    public function parse( QuoteInterface $quoting )
    {

        $schemaOut = $this->inherits->get('schema');
        $logTable = "{$schemaOut}.{$this->relation->get('name')}";
        $logTableQuoted = $quoting->quoteIdent( $logTable );

        $originalTable = $quoting->quoteIdent( $this->relation->get('fullyQualifiedName') );
        $schemaOut = $quoting->quoteIdent( $schemaOut );
        $inherits = $quoting->quoteIdent( $this->inherits->get('fullyQualifiedName') );

        $columnsInherits = $this->inherits->getAttributes()->pluck('name');

        foreach( $this->relation->getAttributes() as $column ) {

            $name = $column->get('name');
            if( in_array( $name, $columnsInherits ) ) {
                throw new \LogicException("Can't create a log table from {$originalTable}. The column names {$name} is reserved.");
            }

            // casting
            $type = $column->getType();
            if( isset( self::$recast[$type->get('name')] ) ) {
                $cast = self::$recast[$type->get('name')];
            } elseif( $type->isEnum() ) {
                $cast = 'text';
            } else {
                $cast = null;
            }

            $insertColumns[] = sprintf(
                "%s%s",
                $quoting->quoteIdent($name),
                $cast ? "::{$cast}" : null
            );

            $createColumns[] = sprintf(
                "%s %s",
                $quoting->quoteIdent($name),
                $quoting->quoteIdent( $cast ?: $type->get('name') )
            );

        }

        // callback to get a quoted name
        $quotedNameGet = function ( $column ) use ( $quoting ) {
            return $quoting->quoteIdent( $column->get('name') );
        };

        $columns = $this->relation->getAttributes()->map( $quotedNameGet );

        $valuesNew = implode(
            ', ',
            $this->inheritedValues + array_map( $this->stringPrepend('NEW.'), $columns )
        );
        $valuesOld = implode(
            ', ',
            $this->inheritedValues + array_map( $this->stringPrepend('OLD.'), $columns )
        );

        $columns = implode( ', ', $this->inherits->getAttributes()->map( $quotedNameGet ) + $columns );

        $insertColumns = implode( ', ', $insertColumns );
        $createColumns = implode( ",\n    ", $createColumns );

        $name = $this->relation->get('name');

        $output = <<<SQL
CREATE TABLE {$logTableQuoted} (
    {$createColumns},
    CONSTRAINT "pk_logs_{$name}" PRIMARY KEY ("logId")
) INHERITS ( {$inherits} );

CREATE TRIGGER "trg_log_{$name}_restrict"
    BEFORE UPDATE OR DELETE
    ON {$logTableQuoted} FOR EACH STATEMENT EXECUTE PROCEDURE common.restrictTrigger();

CREATE INDEX "idx_logPk_{$name}" on {$logTableQuoted} USING btree ( {$this->relation->getPrimaryKeys()->implode(',', $quotedNameGet)} );

COMMENT ON TABLE {$logTableQuoted} IS E'
@normality.match: logs
@normality.makeable: MAKEABLE_EXCEPTION
@normality.isReadonly: READONLY_EXCEPTION
@normality.logging: {$this->relation->get('fullyQualifiedName')}
';

CREATE OR REPLACE FUNCTION {$schemaOut}."{$name}_insert_update"() RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
DECLARE
BEGIN

/*
    RAISE INFO E'Operation: % Schema: % Table: %',
        TG_OP,
        TG_TABLE_SCHEMA,
        TG_TABLE_NAME;
 */

    INSERT INTO {$logTableQuoted} ( {$columns} )
    VALUES( {$valuesNew} );

    RETURN NEW;

END;\$\$;

CREATE OR REPLACE FUNCTION {$schemaOut}."{$name}_delete"() RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
DECLARE
BEGIN

/*
    RAISE INFO E'Operation: % Schema: % Table: %',
        TG_OP,
        TG_TABLE_SCHEMA,
        TG_TABLE_NAME;
 */

    INSERT INTO {$logTableQuoted} ( {$columns} )
    VALUES( {$valuesOld} );

    RETURN NEW;

END;\$\$;

CREATE TRIGGER "trg_log_{$name}_insert_update"
    AFTER INSERT OR UPDATE
    ON {$originalTable} FOR EACH ROW EXECUTE PROCEDURE {$schemaOut}."{$name}_insert_update"();

CREATE TRIGGER "trg_log_{$name}_delete"
    AFTER DELETE
    ON {$originalTable} FOR EACH ROW EXECUTE PROCEDURE {$schemaOut}."{$name}_delete"();

SQL
;

        return $output;

    }

    /**
     * Prepend a string closure
     * @param string $prepend
     */
    private function stringPrepend( $prepend )
    {
        return function( $value ) use ( $prepend ) {
            return $prepend.$value;
        };
    }

}