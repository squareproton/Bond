<?php

namespace Bond\Pg\Converter;

/**
 * Instantiate a object
 */
class NullSafeObjectInstantiation implements ConverterInterface
{

    private $refl;
    private $constructorArgs;

    public function __construct( \ReflectionClass $refl, array $constructorArgs = array() )
    {
        $this->refl = $refl;
        array_unshift( $constructorArgs, null );
        $this->constructorArgs = $constructorArgs;
    }

    public function __invoke($input)
    {
        if( null === $input ) {
            return null;
        }
        $this->constructorArgs[0] = $input;
        return $this->refl->newInstanceArgs( $this->constructorArgs );
    }

}