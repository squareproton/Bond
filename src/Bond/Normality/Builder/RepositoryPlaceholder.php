<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Builder;

use Bond\Normality\Builder\Repository;

use Bond\Normality\Php;
use Bond\Normality\PhpClass;
use Bond\Normality\PhpClassComponent\ClassConstant;
use Bond\Normality\PhpClassComponent\FunctionDeclaration;
use Bond\Normality\PhpClassComponent\VariableDeclaration;

use Bond\Format;
use Bond\MagicGetter;
use Bond\Normality\Sformatf;

/**
 * RespositoryPlaceholder generation
 *
 * @author pete
 */
class RepositoryPlaceholder implements BuilderInterface
{

    use MagicGetter;

    /**
     * @var Bond\Normality\Builder\Repository
     */
    private $repository;

    /**
     * @var Bond\Normality\PhpClass
     */
    private $class;

    /**
     * Standard constructor
     */
    public function __construct( Repository $repository, $namespace )
    {

        $this->repository = $repository;

        $this->class = new PhpClass( $this->repository->class->class, $namespace, false );
        $this->class->addExtends( $this->repository->class->getFullyQualifiedClassname(true) );
        $this->class->setClassComment( $this->repository->class->classComment );

    }

}