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

use Bond\Format;
use Bond\MagicGetter;
use Bond\Normality\Sformatf;

/**
 * Repository
 * @author pete
 */
class Repository implements BuilderInterface
{

    use MagicGetter;

    /**
     * @var Bond\Pg\Catalog\PgClass;
     */
    private $entity;

    /**
     * Form Options
     * @var array
     */
    private $formOptions;

    /**
     * Api Options
     * @var array
     */
    private $apiOptions;

    /**
     * Is entity makeable. Think about the different behaviours you'd want when using a entity
     * that came off a [materialised] view or automatic log setup.
     */
    private $makeable;

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

        $this->class = new PhpClass( $this->entity->pgClass->getEntityName(), $namespace, false );
//        $this->class->addUses('Bond\Entity\Base');

        $this->formOptions = $this->entity->pgClass->getTags('form');
        $this->apiOptions = $this->entity->pgClass->getTags('api');

        $this->setClassComment();
        $this->getClassExtends();
        $this->getClassConstants();

        $this->getApiOptionsDeclaration();
        $this->getChildrenDeclaration();
        $this->getDataTypesDeclaration();
        $this->getFormOptionsDeclaration();
        $this->getInitialPropertiesDeclaration();
        $this->getInstancesMaxAllowedDeclaration();
        $this->getLinksDeclaration();
        $this->getMakeablePropertyDeclaration();
        $this->getNormalityDeclaration();
        $this->getReferencesDeclaration();

        $this->getOptionsResolverFunctionDeclaration();

    }

    /**
     * Class to extend
     * @return string
     */
    private function getClassExtends()
    {
        if( $this->isMultiton() ) {
            $this->class->addUses('\Bond\Repository\Multiton as RM');
            $this->class->addExtends('RM');
        } else {
            $this->class->addUses('\Bond\Repository as R');
            $this->class->addExtends('R');
        }

    }

    /**
     * Get class constants
     */
    private function getClassConstants()
    {
        $pgClass = $this->entity->pgClass;

        $this->class->classComponents[] = new ClassConstant(
            'TABLE',
            new Sformatf( "const TABLE = '%s';", $pgClass->getFullyQualifiedName() )
        );
        $this->class->classComponents[] = new ClassConstant(
            'PARENT',
            new Sformatf(
                "const PARENT = %s;",
                $pgClass->isInherited($parent) ? "'{$parent->getFullyQualifiedName()}'" : 'null'
            )
        );
    }

    /**
     * Entity generated Headers
     * @return array Headers
     */
    private function setClassComment()
    {

        $connectionSettings = $this->entity->pgClass->getCatalog()->db->resource->connectionSettings;

        $header = new Sformatf( <<<TEXT
            ConnectionInfo %s@%s:%s,
            Relation %s
TEXT
            , $connectionSettings->dbname,
            $connectionSettings->user,
            $connectionSettings->host,
            $this->entity->pgClass->getFullyQualifiedName()
        );

        $this->class->setClassComment( $header->docblockify() );

    }

    /**
     * Is this entity a multiton?
     * @return bool
     */
    private function isMultiton()
    {
        return true;
    }

    /**
     * Produce the static::$unsetablePropertiesArray
     */
    private function getInstancesMaxAllowedDeclaration()
    {

        if( !$this->isMultiton() or !array_key_exists( 'instancesMaxAllowed', $this->entity->normalityTags ) ) {
            return array();
        }

        $instancesMaxAllowed = is_numeric( $this->entity->normalityTags['instancesMaxAllowed'] )
            ? (int) $this->entity->normalityTags['instancesMaxAllowed']
            : null
            ;

        $instancesMaxAllowed = Php::varExport( $instancesMaxAllowed, true );

        $this->class->classComponents[] = new VariableDeclaration(
            'instancesMaxAllowed',
            new SFormatf( <<<'PHP'
                /**
                 * Max number of instances stored by our multiton cache
                 * @var array
                 */
                protected $instancesMaxAllowed = %s;
PHP
                , $instancesMaxAllowed
            )
        );

    }

    /**
     * Produce the $this->links var declaration
     */
    private function getLinksDeclaration()
    {

        // we got a max instances allowed in normality tags
        $links = $this->entity->pgClass->getLinks();
        foreach( $links as &$link ) {
            $link = $link->toArray();
        }
        $links = json_encode( $links );
        $links = Php::varExport( $links, true );

        $this->class->classComponents[] = new VariableDeclaration(
            'links',
            new SFormatf( <<<'PHP'
                /**
                 * Link information for this relation
                 * @var array
                 */
                protected $links = %s;
PHP
                , $links
            )
        );

    }

    /**
     * Produce the $this->references declaration
     * @return array
     */
    private function getReferencesDeclaration()
    {

        // we got a max instances allowed in normality tags
        $references = json_encode( $this->entity->pgClass->getReferences() );
        $references = Php::varExport( $references, true );

        $this->class->classComponents[] = new VariableDeclaration(
            'references',
            new SFormatf( <<<'PHP'
                /**
                 * Reference information for this relation
                 * @var array
                 */
                protected $references = %s;
PHP
                , $references
            )
        );

    }

    /**
     * Produce the static::$unsetablePropertiesArray
     * @return array
     */
    private function getNormalityDeclaration()
    {

        $normality = $this->entity->normalityTags;

        // remove options we don't think this Repository needs to store
        unset( $normality['instancesMaxAllowed'] );
        unset( $normality['match'] );

        // we got a max instances allowed in normality tags
        $normality = json_encode( $normality );
        $normality = Php::varExport( $normality, true );

        $this->class->classComponents[] = new VariableDeclaration(
            'normality',
            new SFormatf( <<<'PHP'
                /**
                 * Normality tags from relation
                 * @var array
                 */
                protected $normality = %s;
PHP
                , $normality
            )
        );

    }

    /**
     * Produce the static::$unsetablePropertiesArray
     * @return array
     */
    private function getFormOptionsDeclaration()
    {

        // we got a max instances allowed in normality tags
        $formOptions = json_encode( $this->formOptions );
        $formOptions = Php::varExport( $formOptions, true );

        $this->class->classComponents[] = new VariableDeclaration(
            'formOptions',
            new SFormatf( <<<'PHP'
                /**
                 * Form options from relation
                 * @var array
                 */
                protected $formOptions = %s;
PHP
                , $formOptions
            )
        );

    }

    /**
     * Produce the static::$unsetablePropertiesArray
     * @return array
     */
    private function getApiOptionsDeclaration()
    {

        // we got a max instances allowed in normality tags
        $apiOptions = json_encode( $this->apiOptions );
        $apiOptions = Php::varExport( $apiOptions, true );

        $this->class->classComponents[] = new VariableDeclaration(
            'apiOptions',
            new SFormatf( <<<'PHP'
                /**
                 * API options from relation
                 * @var array
                 */
                protected $apiOptions = %s;
PHP
                , $apiOptions
            )
        );

    }

    /**
     * Produce the static::$makeable property declaration
     * @return array
     */
    private function getMakeablePropertyDeclaration()
    {

        if( $this->entity->pgClass->isView() or $this->entity->pgClass->isMaterialisedView() ) {
            $makeable = 'MAKEABLE_EXCEPTION';
        }

        if( array_key_exists( 'makeable', $this->entity->normalityTags ) ) {
            $makeable = $this->entity->normalityTags['makeable'];
        }

        if( !isset( $makeable ) ) {
            return;
        }

        if( !defined( "\Bond\Repository::{$makeable}" ) ) {
            throw new \InvalidArgumentException("normality.makeable has bad value `{$makeable}`.");
        }

        $this->class->classComponents[] = new VariableDeclaration(
            'resolver',
            new SFormatf( <<<'PHP'
                /**
                 * Makeable
                 * @var string
                 */
                protected $makeable = self::%s;
PHP
                , $makeable
            )
        );

    }

    /**
     * Produce the static::$child declaration
     * @return array
     */
    private function getChildrenDeclaration()
    {

        $children = $this->entity->pgClass->getChildren();

        if( $children->count() === 0 ) {
            return;
        }

        $output = array();
        foreach( $children as $child ) {
            $output[] = $child->getEntityName();
        }
        $names = json_encode( $output );

        $this->class->classComponents[] = new VariableDeclaration(
            'resolver',
            new SFormatf( <<<'PHP'
                /**
                 * Child tables. Array of entities which inherit this entity
                 * @var array
                 */
                protected $children = %s;
PHP
                , $names
            )
        );

    }

    /**
     * Produce the static::$makeable property declaration
     * @return array
     */
    private function getChildOidsDeclaration()
    {

        $children = $this->entity->pgClass->getChildren();

        if( $children->count() === 0 ) {
            return;
        }

        $output = array();
        foreach( $children as $child ) {
            $output[$child->oid] = $child->getEntityName();
        }

        $ids = implode( ', ', array_keys( $output ) );
        $names = implode( ', ', $output );

        $this->class->classComponents[] = new VariableDeclaration(
            'childOids',
            new SFormatf( <<<'PHP'
                /**
                 * ChildOids. The oids of any relations which inherit from this table
                 * @var array
                 */
                protected $childOids = [%s]; // %s
PHP
                , $ids
                , $names
            )
        );

    }

    /**
     * Produce the $initial properties declaration
     */
    private function getInitialPropertiesDeclaration()
    {

        $initialProperties = array();

        foreach( $this->entity->dataTypes as $name => $dataType ) {
            if( $dataType->isInitialProperty() ) {
                $initialProperties[] = "'{$name}'";
            }
        }

        $this->class->classComponents[] = new VariableDeclaration(
            'initialProperties',
            new SFormatf( <<<'PHP'
                /**
                 * Initial properties to be stored by a entity.
                 * @var array
                 */
                protected $initialProperties = [%s];
PHP
                , implode(', ', $initialProperties)
            )
        );

    }

    /**
     * Produce the $dataTypes declaration
     */
    private function getDataTypesDeclaration()
    {

        $arrayDeclaration = [];
        foreach( $this->entity->dataTypes as $name => $dataType ) {
            $arrayDeclaration[] = sprintf(
                "'%s' => %s,",
                $name,
                Php::varExport( $dataType->serialize() )
            );
        }

        $this->class->classComponents[] = new VariableDeclaration(
            'dataTypes',
            new SFormatf( <<<'PHP'
                /**
                 * Datatype information used by this entity. See, Bond\Entity\DataType
                 * @var array
                 */
                protected $dataTypes = [
%s
                ];
PHP
                , (new Format($arrayDeclaration))->indent(20)
            )
        );

    }

    /**
     * Generate a get Options Resolver component for the enity
     */
    private function getOptionsResolverFunctionDeclaration()
    {

        // get required properties
        $required = [];
        $optional = [];
        $allowedValues = [];

        // optional
        foreach( $this->entity->dataTypes as $name => $dataType ) {

            // is required
            if( !$dataType->getDefault() and !$dataType->isNullable() ) {
                $required[] = $name;
            } else {
                $optional[] = $name;
            }

            // set allowed values (currently only works for enums)
            if( $dataType->isEnum($enumName) ) {
                // At the moment this is bugged in Symfony.Don't think it is of vital importance. Ignoring for now.
                # $allowedValues[$name] = $enumName;
            }

        }

        // allowed values declaration
        $allowedValuesPhp = '';
        if( $allowedValues ) {
            $allowedValuesPhp = "\n\$resolver->setAllowedValues(\n    array(\n";
            // at the moment this enum supported only
            foreach( $allowedValues as $column => $enumName ) {
                $allowedValuesPhp .= sprintf(
                    '        %s => Enum::getValues(%s),%s',
                    Php::varExport( $column ),
                    Php::varExport( $enumName ),
                    "\n"
                );
            }
            $allowedValuesPhp .= "    )\n);";
            $allowedValuesPhp = new Format( $allowedValuesPhp );
        }

        // require block
        $requiredPhp = new Format();
        if( $required ) {
            $requiredPhp = "\n\$resolver->setRequired(\n" . (new Format(Php::varExport( $required )))->indent(4) . "\n);";
            $requiredPhp = new Format($requiredPhp);
            $requiredPhp->indent(24);
        }

        // optional
        $optionalPhp = new Format();
        if( $optional ) {
            $optionalPhp = "\n\$resolver->setOptional(\n" . (new Format(Php::varExport( $optional )))->indent(4) . "\n);";
            $optionalPhp = new Format($optionalPhp);
            $optionalPhp->indent(24);
        }

        $this->class->addUses('Symfony\Component\OptionsResolver\OptionsResolver');
        $this->class->classComponents[] = new FunctionDeclaration(
            'resolverGet',
            new Sformatf( <<<'PHP'
                /**
                 * Resolver getter. Singleton
                 * @return Symfony\Component\Options\Resolver
                 */
                public function resolverGet()
                {
                    // do we already have a resolver instance
                    if( !$this->resolver ) {

                        $resolver = new OptionsResolver();%s%s%s
                        $this->resolver = $resolver;

                    }
                    return $this->resolver;
                }
PHP
                , $requiredPhp,
                $optionalPhp,
                $allowedValuesPhp
            )
        );

        $this->class->classComponents[] = new VariableDeclaration(
            'resolver',
            new SFormatf( <<<'PHP'
                /**
                 * Options Resolver
                 * @var array
                 */
                private $resolver;
PHP
            )
        );

    }

 }