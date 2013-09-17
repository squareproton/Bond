<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\Normality\PhpClassComponent;
use Bond\MagicGetter;
use Bond\Flock;
use Bond\Format;

/**
 * Generator
 *
 * @author pete
 */
class PhpClass
{

    use MagicGetter;

    /**
     * @var string class
     */
    private $class;

    /**
     * @var string namespace
     */
    private $namespace;

    /**
     * @var array
     */
    private $usesDeclarations = array();

    /**
     * @var bool
     */
    private $isAbstract = false;

    /**
     * @var array
     */
    private $implements = array();

    /**
     * @var array
     */
    private $extends = array();

    /**
     * @var string
     */
    private $classComment = '';

    /**
     * Misc function store that need to be outputted for this entity.
     * @var array[string]
     */
    private $classComponents;

    /**
     * @param string $class
     * @param string $namespace
     * @param bool isAbstract
     */
    public function __construct( $class, $namespace, $isAbstract = false )
    {
        $this->class = (string) $class;
        $this->namespace = (string) $namespace;
        $this->isAbstract = (bool) $isAbstract;

        $this->classComponents = new Flock( PhpClassComponent::class );
    }

    /**
     * Get the fully qualified classname
     * @param Root qualify the class
     * @return string
     */
    public function getFullyQualifiedClassname($rootQualify = false)
    {
        $class = $this->namespace . '\\' . $this->class;
        if ($rootQualify and 0 !== strpos($class, '\\')  ) {
            $class = '\\'.$class;
        }
        return $class;
    }

    /**
     * Set class comment
     * @param string $comment
     * @return Bond\Normality\PhpClass
     */
    public function setClassComment($comment)
    {
        $this->classComment = new Format( (string) $comment ."\n" );
    }

    /**
     * Add uses clauses to
     * @param mixed string|string[]
     * @return Bond\Normality\PhpClass;
     */
    public function addUses()
    {
        $this->usesDeclarations = $this->addToArrayHelper(
            $this->usesDeclarations,
            func_get_args()
        );
        return $this;
    }

    /**
     * Add class extends
     * @param mixed string|string[]
     * @return Bond\Normality\PhpClass;
     */
    public function addExtends()
    {
        $this->extends = $this->addToArrayHelper(
            $this->extends,
            func_get_args()
        );
        return $this;
    }

    /**
     * Add implements clauses to
     * @param mixed string|string[]
     * @return Bond\Normality\PhpClass;
     */
    public function addImplements()
    {
        $this->implements = $this->addToArrayHelper(
            $this->implements,
            func_get_args()
        );
        return $this;
    }

    /**
     * Util function used by addUses(), addExtends(), addImplements()
     */
    private function addToArrayHelper( $arrayToAddTo, array $arguments = [] )
    {
        // flatten out arguments
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($arguments));

        foreach( $it as $value ) {
            $value = (string) $value;
            if( !in_array( $value, $arrayToAddTo ) and !empty($value) ) {
                $arrayToAddTo[] = $value;
            }
        }

        return $arrayToAddTo;
    }

    public function render()
    {
        $output = new Format(
            sprintf(
                <<<'PHP'
<?php

namespace %s;

%s

%s%s
{%s}
PHP
                , $this->namespace,
                $this->getUsesDeclarations(),
                $this->classComment,
                $this->getClassDeclaration(),
                $this->getClassBody()->indent(4)
            )
        );
        return $output->removeDuplicateEmptyLines();
    }

    /**
     * Get uses declaration
     * @return Bond/Format
     */
    private function getUsesDeclarations()
    {
        asort( $this->usesDeclarations );
        $output = [];
        foreach( $this->usesDeclarations as $namespace ) {
            $output[] = sprintf( 'use %s;', $namespace );
        }
        return new Format( $output );
    }

    /**
     * Get class declaration
     * @return Bond/Format
     */
    private function getClassDeclaration()
    {
        return new Format(
            sprintf(
                '%sclass %s%s%s',
                $this->isAbstract ? 'abstract ' : '',
                $this->class,
                $this->getImplementOrExtendsDeclaration('extends', $this->extends),
                $this->getImplementOrExtendsDeclaration('implements', $this->implements)
            )
        );
    }

    /**
     * Get extends ... implements ... declaration
     * @return Bond/Format
     */
    private function getImplementOrExtendsDeclaration( $prefix, array $array )
    {
        if( !$array ) {
            return '';
        }
        asort( $array );
        return sprintf(
            ' %s %s',
            $prefix,
            implode(', ', $array)
        );
    }

    /**
     * Get class body
     * @return Bond\Format
     */
    private function getClassBody()
    {

        // sort the class components by their name
        $this->classComponents->sort(
            function( PhpClassComponent $a, PhpClassComponent $b ) {
                if ($a::SORT_ORDERING == $b::SORT_ORDERING) {
                    return $a->name < $b->name ? -1 : 1;
                }
                return ($a::SORT_ORDERING < $b::SORT_ORDERING) ? -1 : 1;
            }
        );

        $output = [];
        $lastType = null;
        $lastNumLines = null;
        foreach( $this->classComponents as $component ) {

            // manage strategy for adding new lines
            $componentAsLines = $component->content->toArray();
            if( !$lastType || !$component instanceof $lastType ) {
                $output[] = "\n";
            } elseif( count($componentAsLines) > 1 or $lastNumLines > 1 ) {
                $output[] = "\n";
            }
            $lastType = get_class( $component );
            $lastNumLines = count( $componentAsLines );

            // build the output;
            $output = array_merge( $output, $componentAsLines );

        }
        $output[] = "\n";
        return new Format( $output );

    }

#    /**
#     * Get versioning info
#     * @return string version
#     */
#    protected function getVersion()
#    {
#        return '[unknown]';
#        static $version = false;
#        if ( $version === false ) {
#            $version = exec('hg log -r tip --template \'{rev}\'');
#        }
#        return $version;
#    }
#
#    /**
#     * Get Relation's comment as lines
#     */
#    protected function getRelationCommentAsLines()
#    {
#        $comment = $this->relation->get('comment');
#        $commentLines = array();
#        if( isset( $comment ) ) {
#            $commentLines[] = '';
#            foreach( explode( "\n", $comment ) as $commentLine ) {
#                // handle new lines properly
#                if( $commentLine = trim( $commentLine ) ) {
#                    $commentLines[] = "#{$commentLine}";
#                }
#            }
#        }
#        return $commentLines;
#    }
#
#    /**
#     * Entity generated Headers
#     */
#    protected function getGeneratorHeaders()
#    {
#
#        $output = array();
#
#        // versioning information
#        $output[] = sprintf(
#            'Author %s build %s@%s',
#            get_class( $this ),
#            $this->getVersion(),
#            date('Y-m-d H:i:s')
#        );
#
#        return $output;
#
#    }

}