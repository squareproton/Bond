<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Builder;

use Bond\Normality\Php;
use Bond\Normality\PhpClass;
use Bond\Normality\PhpClassComponent\ClassConstant;
use Bond\Normality\PhpClassComponent\FunctionDeclaration;
use Bond\Normality\PhpClassComponent\VariableDeclaration;

use Bond\Pg\Catalog\PgClass;
use Bond\Pg\Catalog\PgType;

use Bond\Format;
use Bond\MagicGetter;
use Bond\Normality\Sformatf;

/**
 * Entity generation
 *
 * @author pete
 */
class PgRecordConverter implements BuilderInterface
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
     * There is a little (lot!) or overlap ehere between this and the PgConverts.
     * Metaprogramming needs this. Get over it.
     */
    private $handable = [
        'B' => ['bool'],
        'N' => ['int2', 'int4', 'int8', 'numeric', 'float4', 'float8', 'oidvector'],
        'S' => ['varchar', 'char', 'text', 'citext', 'uuid', 'tsvector', 'xml', 'bpchar', 'name', 'int2vector', 'pg_node_tree', 'timestamp'],
        'V' => ['bit', 'varbit'],
        'bytea' => ['bytea']
    ];

    /**
     * The names of the external handlers this object requires for instantiation
     */
    private $externalHandlers = [];

    /**
     * Standard constructor
     */
    public function __construct( Entity $entity, $namespace )
    {

        $this->entity = $entity;

        $this->class = new PhpClass( $this->entity->class->class, $namespace, false );
        $this->class->addExtends( '\Bond\Pg\Converter\PgRecord' );

        // build up string
        $cols = [];
        $i = 0;
        foreach( $this->entity->pgClass->getAttributes() as $col ) {

            // handle value
            $phpDataVar = "\$data[{$i}]";
            $i++;

            $cols[] = sprintf(
                "'%s' => %s, // %s",
                $col->name,
                $this->generatePhpHandlingCode( $col->getType(), $phpDataVar ),
                $col->getType()->name
            );

        }

        $this->generateConstructor();
        $this->generateFromPgFunction($cols);

    }

    public function getRecordRegistrationLine($converterFactoryPhpVar)
    {
        return sprintf(
            "%s->register( %s, ['%s']);",
            $converterFactoryPhpVar,
            $this->getConvertInstantiationLine($converterFactoryPhpVar),
            $this->entity->pgClass->name
        );
    }

    public function getEntityRegistrationLine($converterFactoryPhpVar, $entityManagerPhpVar)
    {
        return sprintf(
            "%s->register( new Entity( %s->getRepository('%s'), %s), ['%s']);",
            $converterFactoryPhpVar,
            $entityManagerPhpVar,
            $this->entity->class->class,
            $this->getConvertInstantiationLine($converterFactoryPhpVar),
            $this->entity->pgClass->name
        );
    }

    private function getConvertInstantiationLine($converterFactoryPhpVar)
    {
        $args = [];
        foreach( $this->externalHandlers as $type ) {
            $args[] = sprintf(
                "%s->getConverter('%s')",
                $converterFactoryPhpVar,
                $type
            );
        }
        return sprintf(
            "new \%s(%s)",
            $this->class->getFullyQualifiedClassname(),
            implode(', ', $args)
        );
    }

    private function generateConstructor()
    {
        if( !$this->externalHandlers ) {
            return;
        }

        $this->class->addUses('Bond\Pg\Converter\ConverterInterface');

        $args = [];
        $assignments = [];
        $docBlockComments = [];

        foreach( $this->externalHandlers as $name ) {

            $this->class->classComponents[] = new VariableDeclaration(
                'unsetableProperties',
                new Sformatf( <<<'PHP'
                    /**
                     * @var Bond\Pg\Converter\ConverterInterface
                     */
                    private $%s;
PHP
                    , $name
                )
            );


            $args[] = sprintf( "ConverterInterface \$%s", $name );
            $assignments[] = sprintf( "\$this->%s = \$%s;", $name, $name );
            $docBlockComments[] = sprintf( " * @param ConvertInterface \$%s", $name );

        }

        $args = implode(', ', $args);
        $assignments = (new Format($assignments))->indentTo(24);
        $docBlockComments = (new Format($docBlockComments))->indentTo(20);

        $this->class->classComponents[] = new FunctionDeclaration(
            '__construct',
            (new SFormatF(
                <<<'PHP'
                    /**
%s
                     * @return void
                     */
                    public function __construct(%s)
                    {
%s
                    }
PHP
                , $docBlockComments
                , $args
                , $assignments
            ))->deindent()
        );

    }

    private function generateFromPgFunction( array $cols )
    {

        $cols = (new Format($cols))->indentTo(28);

        $this->class->classComponents[] = new FunctionDeclaration(
            '__invoke',
            (new SFormatF(
                <<<'PHP'
                    /**
                     * @param string
                     * @return array
                     */
                    public function __invoke($data)
                    {
                        if( null === $data = parent::__invoke($data) ) {
                            return $data;
                        }
                        return [
%s
                        ];
                    }
PHP
                , $cols
            ))->deindent()
        );
    }

    private function generatePhpHandlingCode( PgType $pgType, $phpDataVar )
    {

        foreach( $this->handable as $fn => $supportedTypes ) {
            if( in_array( $pgType->name, $supportedTypes ) ) {
                return call_user_func( [$this, "handle{$fn}"], $phpDataVar );
            }
        }

        // fallback to the typcategory for the obvious choices
        // see, http://www.postgresql.org/docs/9.3/static/catalog-pg-type.html#CATALOG-TYPCATEGORY-TABLE
        switch( $pgType->category ) {
            case 'B':
                return $this->handleB($phpDataVar);
            case 'E':
                return $this->handleS($phpDataVar);
            case 'N':
                return $this->handleN($phpDataVar);
        }

        // is a explodeable array
        if( $pgType->isArray($baseType) ) {
            if( in_array( $baseType->category, ['B','N','V'] ) ) {
                $subHandler = call_user_func( [$this, "handle{$baseType->category}"], "\$v" );
                $callback = "function(\$v){ return {$subHandler}; }";
                return $this->handleExplodeableArray( $phpDataVar, $callback );
            }
        }

        // use external hander
        if( !in_array($pgType->name, $this->externalHandlers) ) {
            $this->externalHandlers[] = $pgType->name;
        }
        return $this->externalHandler($pgType->name, $phpDataVar);

    }

    private function externalHandler( $typeName, $handle )
    {
        return "\$this->{$typeName}->__invoke({$handle})";
    }

    private function handleB( $handle )
    {
        return "null === {$handle} ? null : {$handle} === 't'";
    }

    private function handleN( $handle )
    {
        return "null === {$handle} ? null : {$handle} + 0";
    }

    private function handleS( $handle )
    {
        return "null === {$handle} ? null : {$handle}";
    }

    private function handleV( $handle )
    {
        return "null === {$handle} ? null : bindec($data)";
    }

    private function handleBytea( $handle )
    {
        return "null === {$handle} ? null : pg_unescape_bytea({$handle})";
    }

    private function handleExplodeableArray( $handle, $mapCallback )
    {
        return "null === {$handle} ? null : array_map( {$mapCallback}, explode(',', trim({$handle},'{}')) )";
    }

}