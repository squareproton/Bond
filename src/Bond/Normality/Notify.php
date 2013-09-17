<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\Pg\Catalog\Relation;
use Bond\Pg\Catalog\Attribute;
use Bond\Pg\Catalog\Type;
use Bond\Pg\Catalog\Index;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Bond\MagicGetter;

/**
 * Generator
 * @author pete
 */
class Notify implements SqlInterface
{

    use MagicGetter;

    /**
     * @var namespace
     */
    private $schemaOut;

    /**
     * @var array Relations' we're going to log
     */
    private $relations;

    /**
     * Array of notification sql fns key'd by type
     */
    private $notificationSql = array();

    /**
     * @param string $schemaIn
     * @param string $schemaOut
     */
    public function __construct( $schemaIn, $schemaOut = null )
    {

        $this->catalogRefresh();

        $this->relations = Relation::r()->findAllBySchemaAndRelkind( $schemaIn, 'r' )->filter(
            function($relation){
                // remove log relations and relations with no primary key
                if(
                    $relation->isLogTable() ||
                    $relation->isMaterialisedView() ||
                    $relation->getPrimaryKeys()->count() === 0
                ) {
                    return false;
                }
                return true;
            }
        );

        $this->schemaOut = $schemaOut ?: $schemaIn;

    }

    /**
     * Generate and execute sql which will attach notification triggers to relations
     *
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {

        $outputSql = "";

        foreach( $this->relations as $relation ) {

            $columns = array();
            foreach( $relation->getPrimaryKeys() as $column ) {
                $columns[$column->get('name')] = $column->getType()->getTypeQuery();
            }

            // trigger sql
            if( $this->notificationSql( $quoting, $columns, $functionName, $triggerSql ) ) {
                $outputSql .= $triggerSql;
            }

            $relationName = $relation->get('name');
            $relationQuoted =  $quoting->quoteIdent( $relation->get('fullyQualifiedName') );

            // generate trigger for table
            $outputSql .= <<<SQL
CREATE TRIGGER "trg_notify_{$relationName}_insert" AFTER INSERT ON {$relationQuoted} FOR EACH ROW EXECUTE PROCEDURE "{$functionName}_insert"();
CREATE TRIGGER "trg_notify_{$relationName}_update" AFTER UPDATE ON {$relationQuoted} FOR EACH ROW EXECUTE PROCEDURE "{$functionName}_update"();
CREATE TRIGGER "trg_notify_{$relationName}_delete" AFTER DELETE ON {$relationQuoted} FOR EACH ROW EXECUTE PROCEDURE "{$functionName}_delete"();\n\n
SQL;

        }

        // execute triggers
        return $outputSql;

    }

    /**
     * Generate the triggers which manage notifications for relations with certain function signatures.
     * @param array array(
     *    'columnName' => 'columnType'
     * )
     * @param string name of function (or it's function signature)
     * @param string trigger DDL statements
     *
     * @return bool Is this a new function signature (and correspondingly a new trigger fn)
     */
    public function notificationSql( QuoteInterface $quoting, $columns, &$functionName, &$sql )
    {

        // build the notification trigger sql
        $functionName = array();
        foreach( $columns as $columnName => $type ) {
            $functionName[] = "{$columnName}_{$type}";

        }
        $functionName = implode( '_', $functionName );

        // do we need to generate the notification trigger functions?
        if( !isset( $this->notificationSql[$functionName] ) ) {

            $schemaOut = $quoting->quoteIdent( $this->schemaOut );
            $new = $this->donovan( $quoting, 'NEW.', $columns );
            $old = $this->donovan( $quoting, 'OLD.', $columns );

            // we have a lone primary key?
            if( count($columns) == 1 ) {
                $columnNames = array_keys( $columns );
                $lonePkNew = '    PERFORM pg_notify( TG_TABLE_NAME, NEW.' . $quoting->quoteIdent( $columnNames[0] ) . "::text );\n";
                $lonePkOld = '    PERFORM pg_notify( TG_TABLE_NAME, OLD.' . $quoting->quoteIdent( $columnNames[0] ) . "::text );\n";
            } else {
                $lonePkNew = '';
                $lonePkOld = '';
            }

            // update distinct test
            $distinctTest = array();
            foreach( $columns as $column => $type ) {
                $distinctTest[] = sprintf(
                    'NEW.%1$s IS DISTINCT FROM OLD.%1$s',
                    $quoting->quoteIdent( $column )
                );
            }
            $distinctTest = implode( " OR ", $distinctTest );

            $sql = <<<SQL
--
-- Notify trigger functions begin
--
CREATE FUNCTION {$schemaOut}."{$functionName}_insert"() RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
DECLARE
BEGIN
    PERFORM pg_notify( TG_TABLE_SCHEMA, '{ "op": "' || TG_OP || '", "schema": "' || TG_TABLE_SCHEMA || '", "table": "' || TG_TABLE_NAME || '", "pk": ' || {$new} || ' }' );
{$lonePkNew}
    RETURN NEW;
END;\$\$;\n\n

CREATE FUNCTION {$schemaOut}."{$functionName}_update"() RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
DECLARE
BEGIN

    -- has primary key has changed
    IF {$distinctTest} THEN
        -- !yes
        PERFORM pg_notify( TG_TABLE_SCHEMA, '{ "op": "DELETE", "schema": "' || TG_TABLE_SCHEMA || '", "table": "' || TG_TABLE_NAME || '", "pk": ' || {$old} || ' }' );
        PERFORM pg_notify( TG_TABLE_SCHEMA, '{ "op": "INSERT", "schema": "' || TG_TABLE_SCHEMA || '", "table": "' || TG_TABLE_NAME || '", "pk": ' || {$new} || ' }' );
    {$lonePkNew}    {$lonePkOld}
    -- are the OLD and NEW rows different
    ELSEIF NEW.* IS DISTINCT FROM OLD.* THEN

        PERFORM pg_notify( TG_TABLE_SCHEMA, '{ "op": "' || TG_OP || '", "schema": "' || TG_TABLE_SCHEMA || '", "table": "' || TG_TABLE_NAME || '", "pk": ' || {$new} || ' }' );
    {$lonePkNew}
    END IF;

    RETURN NEW;

END;\$\$;\n\n

CREATE FUNCTION {$schemaOut}."{$functionName}_delete"() RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
DECLARE
BEGIN
    PERFORM pg_notify( TG_TABLE_SCHEMA, '{ "op": "' || TG_OP || '", "schema": "' || TG_TABLE_SCHEMA || '", "table": "' || TG_TABLE_NAME || '", "pk": ' || {$old} || ' }' );
    RETURN OLD;
END;\$\$;\n\n

SQL
;

            $this->notificationSql[$functionName] = $sql;
            return true;

        }

        $sql = $this->notificationSql[$functionName];
        return false;

    }

    /**
     * Produce a sql statement which resolves to a string but is a valid json
     * @return string Sql which evaluates to JSON
     */
    public function donovan( QuoteInterface $quoting, $prefix, $columns )
    {
        $sql = array();
        foreach( $columns as $column => $type ) {
            $sql[] = sprintf(
                'donovan(%s%s)',
                $prefix,
                $quoting->quoteIdent( $column )
            );
        }
        return sprintf(
            "'['||%s||']'",
            implode("||','||", $sql)
        );
    }

    /**
     * Refresh the catalog
     */
    private function catalogRefresh()
    {
        Relation::r()->preload();
        Attribute::r()->preload();
        Type::r()->preload();
        Index::r()->preload();
    }

}