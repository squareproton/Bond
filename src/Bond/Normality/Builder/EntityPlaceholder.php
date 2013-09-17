<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Builder;

use Bond\Normality\Builder\Entity;

use Bond\Normality\Php;
use Bond\Normality\PhpClass;
use Bond\Normality\PhpClassComponent\ClassConstant;
use Bond\Normality\PhpClassComponent\FunctionDeclaration;
use Bond\Normality\PhpClassComponent\VariableDeclaration;

use Bond\Pg\Catalog\PgClass;

use Bond\Format;
use Bond\MagicGetter;
use Bond\Normality\Sformatf;

/**
 * Entity generation
 *
 * @author pete
 */
class EntityPlaceholder implements BuilderInterface
{

    use MagicGetter;

    /**
     * @var Bond\Pg\Catalog\PgClass
     */
    private $entity;

    /**
     * @var Bond\Normality\PhpClass
     */
    private $class;

    /**
     * Standard constructor
     */
    public function __construct( Entity $entity, $namespace )
    {

        $this->entity = $entity;

        $this->class = new PhpClass( $this->entity->class->class, $namespace, false );
        $this->class->addExtends( $this->entity->class->getFullyQualifiedClassname(true) );
        $this->class->setClassComment( $this->entity->class->classComment );
        $this->class->addUses( 'Symfony\Component\Validator\Mapping\ClassMetadata' );

        $this->class->classComponents[] = new FunctionDeclaration(
            'loadValidatorMetadata',
            (new Format(
                <<<'PHP'
                /**
                 * Symfony Validator Metadata.
                 * WARNING! Workaround. Symfony validator uses its own inheritance mechanism. This unusual setup is designed to short circuit that.
                 *
                 * @param ClassMetadata $metadata
                 * @return void
                 */
                public static function loadValidatorMetadata( ClassMetadata $metadata )
                {
                    parent::_loadValidatorMetaData( $metadata );
                }
PHP
            ))->deindent()
        );

    }

}