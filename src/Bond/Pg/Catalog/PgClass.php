<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog;

use Bond\Pg\Catalog;
use Bond\Pg\Catalog\PgIndex;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Bond\Container as BaseContainer;

class PgClass implements SqlInterface
{

    use Sugar;

    private $indexes;
    private $columns;
    private $links;

    /**
     * {@inheritDoc}
     */
    public function parse( QuoteInterface $quoting )
    {
        return $quoting->quoteIdent( $this->getFullyQualifiedName() );
    }

    public function getFullyQualifiedName()
    {
        return "{$this->schema}.{$this->name}";
    }

    public function getParent()
    {
        return $this->catalog->pgClasses->findOneByOid( $this->parent );
    }

    public function getChildren()
    {
        return $this->catalog->pgClasses->findByParent( $this->oid );
    }

    /**
     * Populate $this->indexes
     * @return array
     */
    public function getIndexes( $includeParents = true )
    {

        // lazy load indexes
        if( !isset( $this->indexes ) ) {

            $this->indexes = $this->catalog->pgIndexes->findByIndrelid( $this->oid );

            // are we inheriting off another table
            if( $parent = $this->getParent() ) {

                foreach( $parent->getIndexes() as $parentIndex ) {

                    $data = $parentIndex->all();
                    $data['inherited'] = true;
                    $data['columns'] = [];

                    foreach( $parentIndex->indkey as $indkey ) {
                        $columns[] = sprintf( "%s.%s", $this->oid, $indkey );
                    }

                    $this->indexes->add(
                        new PgIndex( $this->catalog, $data )
                    );

                }

            }

        }

        // include parent indexes
        if( $includeParents ) {
            return $this->indexes->copy();
        } else {
            return $this->indexes->copy()->removeByInherited( true );
        }

    }

    /**
     * @return Bond\Container
     */
    public function getPrimaryKeys()
    {
        $attributes = new BaseContainer();
        foreach( $this->getIndexes() as $index ) {
            if( $index->indisprimary ) {
                if( null === $index->columns ) {
                    print_r( $index );
                    die();
                }

                foreach( $index->columns as $column ) {
                    $attributes->add(
                        $this->catalog->pgAttributes->findByKey( $column )
                    );
                }

            }

        }
        return $attributes;
    }

    /**
     * @return Bond\Container
     */
    public function getUniqueAttributes()
    {
        $attributes = new BaseContainer();
        foreach( $this->getIndexes() as $index ) {
            if( count($index->columns) === 1 and $index->indisunique ) {
                $attributes->add(
                    $this->catalog->pgAttributes->findOneByKey( $index->columns[0] )
                );
            }
        }
        return $attributes;
    }

    /**
     * Get normality tags
     */
    public function getTags( $type = 'normality' )
    {
        return \Bond\extract_tags( $this->comment, $type );
    }

    /**
     * The name of this relation as per the Bond convention
     * Uppercase first letter. Replace '_' with ''
     *
     * @return string
     */
    public function getEntityName()
    {

        // we got a entityname set already
        $tags = $this->getTags();

        if( isset( $tags['entity-name'] ) ) {
            return $tags['entity-name'];
        }

        $name = ucwords( $this->name );
        $name = \str_replace( '_', '', $name );

        return $name;

    }

    /**
     * Get array of links to this table
     * @return array
     */
    public function getLinks()
    {

        if( isset( $this->links ) ) {
            return $this->links;
        }

        // build links
        $this->links = array();

        foreach( $this->getAttributes() as $column ) {

            /* This relation has a link if it has a attribute which has all of the following
             *   1. Is unique and is referenced by another attribute
                    TODO. Pete: We don't currently support dual column foreign key constraints
             *   2. This another attribute is one half of a dualcolumn primary key
             *      TODO. Pete: I think this can be relaxed a little. We just need a unique constraint which references two tables
             *   3. The other half of the primary references another unique constraint in another table
             */
            // See 1.
            if( $column->isUnique() and ( $references = $column->getIsReferencedBy() ) ) {

                foreach( $references as $reference ) {

                    // See 2.
                    if( $reference->isPrimaryKey() and count( $primaryKeys = $reference->getRelation()->getPrimaryKeys() ) == 2 ) {

                        $primaryKeys->remove( $reference );
                        $otherHalfofPrimaryKey = $primaryKeys->pop();

                        // See 3.
                        $otherReferences = $otherHalfofPrimaryKey->getReferences();
                        $foreignEntities = new BaseContainer();
                        foreach( $otherReferences as $otherReference ) {

                            // Pete. We might not need the unique check here because foreign keys already have uniqueness enforced
                            if( $otherReference->isUnique() and $otherReference->getRelation() !== $this ) {

                                $foreignEntities[] = $foreignEntities->add( $otherReference->getRelation() );
                                $foreignEntityReference = $otherReference;

                            }

                        }

                        // have we a link?
                        if( $foreignEntities ) {

                            $sourceEntity = $this->getEntityName();
                            $linkEntity = $otherHalfofPrimaryKey->getRelation()->getEntityName();

                            $refSource = array( $column->name, $reference->name );
                            $refForeign = array( $otherHalfofPrimaryKey->name, $foreignEntityReference->name );

                            // get columns and look for a ranking columns
                            $possibleRankingAttributes = $otherHalfofPrimaryKey->getRelation()->getAttributes();
                            $possibleRankingAttributes->remove( $otherHalfofPrimaryKey, $reference );

                            $sortColumn = null;
                            $rankingRegex = "/^{$otherHalfofPrimaryKey->name}_?(r|rank|ranking)$/i";
                            foreach( $possibleRankingAttributes as $ranking ) {

                                if( $ranking->getType()->isNumeric() and preg_match( $rankingRegex, $ranking->name ) ) {
                                    $sortColumn = $ranking->name;
                                    break;
                                }

                            }

                            // sort foreignEntities, we want 'root' entity first then alphabetical
                            $foreignEntities = $foreignEntities
                                ->sort(
                                    function($a, $b){
                                        if( $a->isInherited() and !$b->isInherited() ) {
                                            return 1;
                                        }
                                        return $a->getEntityName() < $b->getEntityName() ? -1 : 1;
                                    }
                                )
                                ->map(function ($e) {
                                    return $e->getEntityName();
                                });

                            $this->links[$linkEntity] = new Link(
                                $sourceEntity,
                                $linkEntity,
                                array_values( $foreignEntities ),
                                $refSource,
                                $refForeign,
                                $sortColumn
                            );

                        }

                    }

                }

            }

        }

        return $this->links;

    }

    /**
     * Get array of references to this table
     * eg,
     *  array(
     *      'a11.a1_id' => array( 'A11', 'a1_id', 0 )
     *      ...
     *  )
     * @return array
     */
    public function getReferences()
    {

        // build links
        $references = array();

        foreach( $this->getAttributes() as $column ) {

            // A relation has a reference if one of its attributes is referenced by another
            if( $column->isUnique() and ( $refs = $column->getIsReferencedBy() ) ) {

                foreach( $refs as $reference ) {

                    $name = sprintf(
                        "%s.%s",
                        $reference->getRelation()->getEntityName(),
                        $reference->name
                    );

                    $references[$name] = array(
                        $reference->getRelation()->getEntityName(),
                        $reference->name,
                        $reference->isUnique() ? 0 : 1
                    );

                }

            }

        }

        return $references;

    }

    /**
     * Get all columns on this relation
     * @return Container
     */
    public function getAttributes( $includeParents = true )
    {
        if( !$this->columns ) {
            $this->columns = $this->catalog->pgAttributes->findByAttrelid( $this->oid )->sortByAttnum();
        }

        $output = $this->columns->copy();

        // include parents
        if( !$includeParents ) {
            $output->filter(
                function($attribute){
                    return !$attribute->isInherited();
                }
            );
        }

        return $output;
    }

    /**
     * @return Bond\Pg\Catalog\PgAttribute
     */
    public function getAttributeByName($name)
    {
        return $this->getAttributes()->findByName($name)->pop();
    }

    /**
     * Is this relation a view
     * @return bool
     */
    public function isView()
    {
        return $this->relkind === 'v';
    }

    /**
     * Is this relation a system generated log table
     * @return bool
     */
    public function isSystemLogTable()
    {
        $tags = $this->getTags();
        return array_key_exists( 'logging', $tags );
    }

    /**
     * Is this relation a logTable (ie, is it tagged logtable
     * @param bool The default value to return in the event of logTable status not defined
     * @return bool
     */
    public function isLogTable( $default = false )
    {
        $tags = $this->getTags();
        if( array_key_exists( 'isLogTable', $tags ) ) {
            return boolval( $tags['isLogTable'] );
        } elseif( array_key_exists( 'logging', $tags ) ) {
            return true;
        }
        return (bool) $default;
    }

    /**
     * Is this relation a materializedView (ie, is it tagged logtable)
     * @param bool The default value to return in the event of materializedView status not defined
     * @return bool
     */
    public function isMaterialisedView( $default = false )
    {
        $tags = $this->getTags();
        if( array_key_exists( 'isMaterialisedView', $tags ) ) {
            return boolval( $tags['isMaterialisedView'] );
        }
        return (bool) $default;
    }

    /**
     * Is this relation inherited from another
     * @return bool
     */
    public function isInherited( &$parent = null )
    {
        $parent = $this->getParent();
        return (bool) $parent;
    }

}