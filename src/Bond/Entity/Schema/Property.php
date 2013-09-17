<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Schema;

use Bond\Entity\Base;

class Property
{

    private $property;
    private $processor;
    private $alias;

    /**
     * Constructor
     * @param string $propertyName
     * @param mixed $alias
     * @return Schema
     */
    public function __construct( $property, $processor = null, $alias = null )
    {
        $this->property = $property;
        $this->processor = $processor;
        $this->alias = $alias;
    }

    public function aliasGet()
    {
        return coalesce( $this->property, $this->alias );
    }

    public function valueGet( Base $entity = null )
    {
        if( !$entity ) {
            return null;
        }
        $value = $entity->get( $property );

        // got a processor
        if( $this->processor and is_callable( $this->processor ) ) {
            $value = call_user_func( $this->processor, $value );
        }

        return $value;
    }

}