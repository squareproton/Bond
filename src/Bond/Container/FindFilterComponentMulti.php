<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container;

use Bond\Container\FindFilterComponentInterface;
use Bond\MagicGetter;

class FindFilterComponentMulti implements FindFilterComponentInterface
{

    use MagicGetter;

    /**
     * The FindFilterComponent we're going to wrap
     * @var Bond\Container\FindFilterComponent;
     */
    public $findFilterComponent;

    /**
     * One of our allowed operators
     * @var string. See, FilterComponentFactory::$operations
     */
    protected $operation;

    /**
     * The value which we're going to compare our Entity to.
     * @var mixed
     */
    public $value;

    /**
     * Standard constructor
     * @param Bond\Container\FindFilterComponent
     * @param string $operation
     * @param mixed $value
     */
    public function __construct( array $findFilterComponents, $operation, $value )
    {
        $this->findFilterComponents = $findFilterComponents;
        $this->operation = $operation;
        $this->value = $value;
    }

    /**
     * Pass a entity through a filter. Does it match?
     * @param mixed $object we're going to filter on
     * @return bool
     */
    public function check( $obj )
    {
        foreach( $this->findFilterComponents as $findFilterComponent ) {
            if( $findFilterComponent->check($obj) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Standard getter $cannotMatch
     * @return bool
     */
    public function getCannotMatch( callable $cannotMatch )
    {
        return \Bond\array_check( $cannotMatch, $this->findFilterComponents );
    }

}