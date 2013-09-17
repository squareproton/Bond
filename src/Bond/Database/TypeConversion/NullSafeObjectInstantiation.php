<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database\TypeConversion;

use Bond\Database\TypeConversion;

/**
 * Instantiate a object
 */
class NullSafeObjectInstantiation extends TypeConversion
{

    protected $refl;
    protected $constructorArgs;

    public function __construct( $type, $class, array $constructorArgs = array() )
    {

        parent::__construct($type);

        $this->refl = new \ReflectionClass( $class );
        array_unshift( $constructorArgs, null );
        $this->constructorArgs = $constructorArgs;

    }

    /**
     * Invoke the object
     */
    public function __invoke($input)
    {
        if( null === $input ) {
            return null;
        }

        $this->constructorArgs[0] = $input;
        return $this->refl->newInstanceArgs( $this->constructorArgs );
    }

}