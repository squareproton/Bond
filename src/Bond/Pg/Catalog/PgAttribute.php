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

use Bond\Container\ContainerObjectAccess as BaseContainer;

class PgAttribute implements SqlInterface
{

    use Sugar {
        Sugar::__construct as Sugar__construct;
    }

    // pre cached references / is referenced by code
    protected $references;
    protected $isReferencedBy;

    protected $relation;

    public function __construct()
    {
        call_user_func_array(
            [$this, 'Sugar__construct'],
            func_get_args()
        );
        $this->references = new BaseContainer();
        $this->isReferencedBy = new BaseContainer();
    }

    /**
     * {@inheritDoc}
     */
    public function parse( QuoteInterface $quoting )
    {
        return $quoting->quoteIdent( $this->getFullyQualifiedName() );
    }

    public function getRelation()
    {
        if( !$this->relation ) {
            $this->relation = $this->catalog->pgClasses->findOneByOid( $this->attrelid );
        }
        return $this->relation;
    }

    public function getType()
    {
        return $this->catalog->pgTypes->findOneByOid( $this->typeOid );
    }

    public function getFullyQualifiedName()
    {
        return "{$this->getRelation()->name}.{$this->name}";
    }

    public function getReferences()
    {
        return $this->references->copy();
    }

    public function getIsReferencedBy()
    {
        return $this->isReferencedBy->copy();
    }

    public function getLength()
    {
        $type = $this->getType();

        switch ($typeName) {
            case 'bpchar':
                return (int) $this->data['length'];
            default:
                return $type->length;
        }
    }

    /**
     * Is this column part of the primary key
     * @return bool
     */
    public function isPrimaryKey()
    {
        return $this->getRelation()->getPrimaryKeys()->contains($this);
    }

    /**
     * Is this column a unique one?
     * @return bool
     */
    public function isUnique()
    {
        return $this->getRelation()->getUniqueAttributes()->contains($this);
    }

    /**
     * Get all inherited Attributes
     * @return Container
     */
    public function getChildren()
    {

        $output = new BaseContainer();

        foreach( $this->getRelation()->getChildren() as $childRelation ) {
            $childAttribute = $childRelation->getAttributeByName($this->name);
            $output->add(
                $childAttribute,
                $childAttribute->getChildren()
            );
        }

        return $output;

    }

    public function getTags( $type )
    {
        return \Bond\extract_tags( $this->comment, $type );
    }

//    public function get($key, $inputValidate = null, $source = null, \Bond\RecordManager\Task $task = null)
//    {
//
//        case 'definition':
//            return sprintf(
//                '%s %s',
//                Query::quoteIdentifier( $this->get('name') ),
//                Query::quoteIdentifier( $this->getType()->get('fullyQualifiedName') )
//            );
//
//    }

    /**
     * Is this column inheritied from another relation
     * @param Column The original inherited column.
     * @return bool
     */
    public function isInherited( &$originalColumn = null )
    {

        $inhcount = $this->attinhcount;
        if( $inhcount == 0 ) {
            $originalColumn = null;
            return false;
        }

        // short cut to prevent the work of finding the original column
        if( func_num_args() === 0 ) {
            return true;
        }

        // keep knocking off the parents until we reach the root
        $originalRelation = $this->getRelation();

        do {
            $originalRelation = $originalRelation->getParent();
        } while( --$inhcount > 0 );

        $originalColumn = $originalRelation->getAttributeByName( $this->name );
        return true;

    }

    /**
     * Add a reference to this attribute
     * @param Attribute $primaryKey The attribute we're referencing
     */
    public function addReference( PgAttribute $primaryKey, $includeChildTables = true )
    {

        $this->references->add( $primaryKey );
        $primaryKey->isReferencedBy->add( $this );

        if( $includeChildTables || true ) {
            foreach( $primaryKey->getRelation()->getChildren() as $child ) {
                $this->addReference($child->getAttributeByName($primaryKey->name), true);
            }
        }

        return;

        // TODO. Add support for inheritance
        $query = new Query(
            "SELECT oid::text || '.' || %attnum:int%::text FROM dev.relationDescendants( %oid:int%::oid ) as _ ( oid );",
            array(
                'attnum' => $pk->get('attnum'),
                'oid' => $pk->get('relation')->get('oid')
            )
        );

    }

    /**
     * Is this column a sequence
     * @return Sequence|null
     */
    public function getSequence()
    {
        if( preg_match( "/^nextval\\('([^']+)'::regclass\\)$/", $this->default, $matches ) ) {
            return $this->catalog->pgClasses->findOneByName($matches[1]);
        }
        return null;
    }

    /**
     * Return a reference to the entity
     * @return string
     */
    public function getEntity()
    {

        // primary key only
        $references = $this->getReferences();

        // debug // print_r( $references->map(function($e){ return $e->get('fullyQualifiedName');}) );
        foreach( $references as $reference ) {

            // only manage references where the reference column is the primary key
            if( $reference->isPrimaryKey() ) {
                return array(
                    'entity' => 'normality',
                    'normality' => $reference->getRelation()->getEntityName(),
                );
            }

        }

        // datatypes we understand and have defined objects for
        // have a helper method in Entity::staticHelper
        switch( $this->getType()->name ) {

            case 'StockState':

                return array (
                    'entity' => 'StockState',
                );

            case 'hstore':

                return array(
                    'entity' => 'Hstore',
                );

            case 'json':

                return array(
                    'entity' => 'Json',
                );

            case 'inet':

                return array(
                    'entity' => 'Inet',
                );

            case 'timestamp':

                return array(
                    'entity' => 'DateTime',
                );

            case 'tsrange':

                return array(
                    'entity' => 'DateRange',
                );

            case 'oid':

                return array(
                    'entity' => 'PgLargeObject',
                );

        }

        return array();

    }

    /**
     * Is this column a zombie-column candidate?
     * Happens when at least one of the following is true,
     *
     *      1. A column is a primary key fragment and references something else. [Pete. This is essentially the same as 2. because primary key fragments have to be not null]
     *      2. A column references something and is not null
     * TODO 3. A column is marked zombie by a normality attribute
     *
     * @return bool
     */
    public function isZombie()
    {
        $ref = $this->getReferences();

        if( $this->isPrimaryKey() and !$ref->isEmpty() ) {
            return true;
        }

        if( $this->notNull and !$ref->isEmpty() ) {
            return true;
        }

        // comment
        // if( \Bond\extract_tags( $this->get('comment') )
        return false;
    }

}