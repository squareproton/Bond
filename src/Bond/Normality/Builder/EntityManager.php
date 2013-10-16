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
    public function __construct( $fullyQualifiedClass, array $registrations = [], array $pgRecordConverters )
    {

        $this->class = new PhpClass(
            \Bond\get_unqualified_class($fullyQualifiedClass),
            \Bond\get_namespace($fullyQualifiedClass)
        );

        $this->class->addUses( 'Bond\Pg\Converter\Entity' );

        // registrations
        foreach( $registrations as &$registration ) {
            $registration = sprintf(
                "\$em->register( '%s', '%s' );",
                addslashes( $registration[0] ),
                addslashes( $registration[1] )
            );
        }
        $registrations = new Format($registrations);

        // type converstions
        $converters = [];
        $converterFactoryPhpVar = "\$cf";
        foreach( $pgRecordConverters as $converter ) {
            // $converters[] = $converter->getRecordRegistrationLine($converterFactoryPhpVar, '$em' );
            $converters[] = $converter->getEntityRegistrationLine($converterFactoryPhpVar, '$em' );
        }
        $converters = new Format($converters);


        $this->class->classComponents[] = new FunctionDeclaration(
            '__construct',
            new Sformatf( <<<'PHP'
                /**
                 * Register a set of entities with a EntityManager
                 * Register database type conversion code with a EntityManager
                 * @inheritDoc
                 */
                public function __construct( \Bond\EntityManager $em )
                {
%s

                    %s = $em->db->converterFactory;
%s

                }
PHP
                , $registrations->indent(20)
                , $converterFactoryPhpVar
                , $converters->indent(20)
            )
        );

    }

}