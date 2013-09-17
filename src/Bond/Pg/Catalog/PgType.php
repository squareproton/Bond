<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog;

use Bond\Pg\Catalog;
use Bond\Sql\SqlInterface;
use Bond\Sql\QuoteInterface;

/**
 * Manager of PgTypes
 * See, http://www.postgresql.org/docs/8.4/interactive/catalog-pg-type.html
 *
 * @author pete
 */
class PgType implements SqlInterface
{

    // http://www.postgresql.org/docs/8.4/interactive/catalog-pg-type.html
    protected static $category = array (
        'A' => 'array',
        'B' => 'boolean',
        'C' => 'composite',
        'D' => 'date/time',
        'E' => 'enum',
        'G' => 'geometric',
        'I' => 'network',
        'N' => 'numeric',
        'P' => 'pseudo',
        'S' => 'string',
        'T' => 'timespan',
        'U' => 'user',
        'V' => 'bitstring',
        'X' => 'unknown',
    );

    use Sugar;

    /**
     * {@inheritDoc}
     */
    public function parse( QuoteInterface $quoting )
    {
        return $quoting->quoteIdent( $this->getFullyQualifiedName() );
    }

    /**
     * Is this type a enum
     * @return bool
     */
    public function isEnum()
    {
        return $this->category === 'E';
    }

    /**
     * Is this type a bool
     * @return bool
     */
    public function isBool()
    {
        return $this->category === 'B';
    }

    /**
     * See, http://www.postgresql.org/docs/8.4/static/catalog-pg-type.html#CATALOG-TYPCATEGORY-TABLE
     * Is this type numeric
     * @return bool.
     */
    public function isNumeric()
    {
        return $this->category === 'N';
    }

    public function getFullyQualifiedName()
    {
        return "{$this->schema}.{$this->name}";
    }

    /**
     * Mapper for postgres types and Query::validate types
     * Take postgres typname and convert into a Query::validate type
     *
     * @param string Typname as might be returned by $this->get('name')
     * @return string Type as understood by Bond\Pg\Query::validate()
     */
    public function getTypeQuery()
    {

        switch( true ) {

            case $this->name === 'timestamp':
            case $this->name === 'bool':

                return $this->name;

            case $this->isEnum(): // enum's behave like strings

                return $this->name;

            case $this->name === 'bpchar': // "blank-padded char", the internal name of the 'character' data type
            case $this->name === 'text':

                return 'text';

            case $this->name === 'int2':
            case $this->name === 'int4':
            case $this->name === 'int8':

                return 'int';

            default:

                return $this->name;
                throw new \UnexpectedValueException( "type::pgTypeToQueryValidateType doesnt know about type `{$type}`" );

        }

    }

}