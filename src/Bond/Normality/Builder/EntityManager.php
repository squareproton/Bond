<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Builder;

use Bond\Normality\PhpClass;
use Bond\Normality\PhpClassComponent\FunctionDeclaration;

use Bond\Format;
use Bond\MagicGetter;
use Bond\Normality\Sformatf;

/**
 * RespositoryPlaceholder generation
 *
 * @author pete
 */
class EntityManager implements BuilderInterface
{

    use MagicGetter;

    /**
     * @var Bond\Normality\PhpClass
     */
    private $class;

    /**
     * Standard constructor
     */
    public function __construct( $fullyQualifiedClass, array $registrations = [] )
    {

        $this->class = new PhpClass(
            \Bond\get_unqualified_class($fullyQualifiedClass),
            \Bond\get_namespace($fullyQualifiedClass),
            false
        );

        if( !$registrations ) {
            return;
        }

        foreach( $registrations as &$registration ) {
            $registration = sprintf(
                "\$em->register( '%s', '%s' );",
                addslashes( $registration[0] ),
                addslashes( $registration[1] )
            );
        }
        $registrations = new Format($registrations);

        $this->class->classComponents[] = new FunctionDeclaration(
            '__construct',
            new Sformatf( <<<'PHP'
                /**
                 * Register a set of entities with a EntityManager
                 * @inheritDoc
                 */
                public function __construct( \Bond\EntityManager $em )
                {
%s
                }
PHP
                , $registrations->indent(20)
            )
        );

    }

}