<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity;

use Bond\Pg\Catalog\Attribute;
use Bond\Pg\Catalog\Relation;

use Bond\Sql\Query;

/**
 * Description of DataType
 *
 * @author pete
 */
class Link
{

    /**
     * From perspectve of.... The relation from who's perspective we're looking from
     * @var string
     */
    private $sourceEntity;

    /**
     * The name of the link table, think ItemLinkNotes
     * @var string
     */
    private $linkEntity;

    /**
     * Foreign tables
     * @var array
     */
    private $foreignEntities = array();

    /**
     * The column in $sourceEntity which is referenced by $linkEntity.$sourceColumn
     * @var string
     */
    private $refSource = array();

    /**
     * The column in $linkEntity which references foreign tables
     */
    private $refForeign = array();

    /**
     * The ranking column (if exists)
     * @var string
     */
    private $sortColumn = null;

    /**
     * Standard constructor
     * @param string $name
     * @param array $data
     */
    public function __construct( $sourceEntity, $linkEntity, $foreignEntities, $refSource, $refForeign, $sortColumn )
    {
        $this->sourceEntity = $sourceEntity;
        $this->linkEntity = $linkEntity;
        $this->foreignEntities = $foreignEntities;
        $this->refSource = $refSource;
        $this->refForeign = $refForeign;
        $this->sortColumn = $sortColumn;
    }

    public function toArray()
    {
        return array(
            $this->sourceEntity,
            $this->linkEntity,
            $this->foreignEntities,
            $this->refSource,
            $this->refForeign,
            $this->sortColumn,
        );
    }

    public static function fromArray( array $data )
    {
        return new self( $data[0], $data[1], $data[2], $data[3], $data[4], $data[5] );
    }

    public function __get( $key )
    {
        switch( $key ) {
            case 'sourceEntity':
            case 'linkEntity':
            case 'foreignEntities':
            case 'refSource':
            case 'refForeign':
            case 'sortColumn':
                return $this->$key;
        }
        throw new \InvalidArgumentException("Link. Don't know about __get( `{$key}` )" );
    }

    public function __isset( $key )
    {
        return isset( $this->$key );
    }

}