<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container;

use Bond\Container\PropertyMapper;
use Bond\Exception\NeedsOverloadingException;
use Bond\MagicGetter;

abstract class FindFilterComponent implements FindFilterComponentInterface
{

    use MagicGetter;

    /**
     * Property name of entity this filter will be working on
     * @var string
     */
    protected $propertyMapper;

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
     * Standard __construct
     * @param string $name
     * @param string $operation
     * @param mixed $value
     */
    public function __construct( PropertyMapperInterface $propertyMapper, $operation, $value )
    {
        $this->propertyMapper = $propertyMapper;
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
        throw new NeedsOverloadingException( $this, __METHOD__ );
    }

    /**
     * Standard setter
     * @param callable
     * @return bool
     */
    public function getCannotMatch( callable $cannotMatch )
    {
        return (bool) call_user_func(
            $cannotMatch,
            $this->value
        );
    }

}