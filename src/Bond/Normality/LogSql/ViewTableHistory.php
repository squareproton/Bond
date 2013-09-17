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
class ViewTableHistory implements SqlInterface
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
        $relationLogParent = $quoting->quoteIdent( $this->relationLog->get('parent')->get('fullyQualifiedName') );
        $relationOriginal = $quoting->quoteIdent( $this->relationOriginal->get('fullyQualifiedName') );
        $schema = $quoting->quoteIdent( $this->relationLog->get('schema') );

        // get a attribute name quoted
        $getNameQuoted = function ( $attribute ) use ( $quoting ) {
            return $quoting->quoteIdent( $attribute->get('name' ) );
        };

        $output = <<<SQL
CREATE VIEW {$schema}."v{$this->relationLog->get('name')}History" AS
SELECT
    {$this->relationOriginal->getKeySql($quoting)}::key as key,
    array_agg( ROW( {$this->relationOriginal->getAttributes()->implode(', ', $getNameQuoted)} )::{$relationOriginal} ) as datas,
    array_agg( ROW( {$this->relationLog->get('parent')->getAttributes()->implode(', ', $getNameQuoted)} )::{$relationLogParent} ) AS info
FROM
    ( SELECT * FROM {$relationLog} ORDER BY "logId" DESC ) _
GROUP BY
    {$this->relationOriginal->getPrimaryKeys()->implode(', ', $getNameQuoted)}
;

COMMENT ON VIEW {$schema}."v{$this->relationLog->get('name')}History" IS E'
@normality.match: logs
@normality.makeable: MAKEABLE_EXCEPTION
@normality.isReadonly: READONLY_EXCEPTION
@normality.logging: {$this->relationOriginal->get('fullyQualifiedName')}
';

SQL;

        return $output;

    }

}