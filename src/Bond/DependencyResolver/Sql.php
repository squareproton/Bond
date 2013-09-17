<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver;

use Bond\DependencyResolver;
use Bond\DependencyResolver\ResolverList;
use Bond\DependencyResolver\Exception\BadSqlResolverBlock;
use Bond\DependencyResolver\Exception\ResolutionException;

use Bond\Pg;
use Bond\Sql\Raw;

use Bond\Entity\Types\Json;
use Bond\Exception\BadJsonException;

use Bond\DependencyResolver\Exception\DependencyMissingException;

class Sql extends DependencyResolver
{

    const V2_RESOLVER_BLOCK_REGEX = '/^\/\\*\\*\\W*resolver(.*)\\*\\//Ums';

    private $sql;
    private $pg;

    public function __construct( $id, Pg $pg, $sql, array $options = array() )
    {
        $this->pg = $pg;
        $this->sql = $sql;
        $resolve = function() {
            try {
                // have search_path?
                if( $searchPath = $this->getSearchPath() ) {
                    $this->pg->setParameter( 'search_path', $searchPath );
                }
                // execute sql
                $output = $this->pg->query( new Raw( $this->sql ) );
                // restore search path if appropriate
                if( $searchPath ) {
                    $this->pg->restoreParameter( 'search_path' );
                }
            } catch ( \Exception $e ) {
                throw new ResolutionException( $this->id, $e );
            }
        };
        parent::__construct( $id, $resolve, $options );
    }

    public function getSql()
    {
        return $this->sql;
    }

    public function setSqlDepends( ResolverList $list, $throwExceptionIfDependNotInList = true )
    {
        foreach( $this->getSqlDepends() as $_depend ) {
            if( $depend = $list->getById($_depend) ) {
                $this->addDependency( $depend );
            } elseif( $throwExceptionIfDependNotInList ) {
                throw new DependencyMissingException($_depend, $this, $list);
            }
        }
    }

    public function getSqlDepends()
    {
        $options = $this->getResolverBlock();
        return isset( $options['depends'] ) ? $options['depends'] : array();
    }

    public function getSearchPath()
    {
        $options = $this->getResolverBlock();
        return isset( $options['searchPath'] ) ? $options['searchPath'] : null;
    }

    public function getResolverBlock()
    {
        // strategy 1. Use resolver block.
        if( preg_match( self::V2_RESOLVER_BLOCK_REGEX, $this->sql, $matches ) ) {
            try {
                $json = new Json( $matches[1] );
                $get = $json->get();
            } catch ( BadJsonException $e ) {
                throw new BadSqlResolverBlock( $this, $matches[0] );
            }
            return $get;
        }
        throw new \Exception("You need a docblock comment for asset '{$this->id}'");
    }

    public function modifyResolverBlock( callable $modifier )
    {
        // reformat / reindent any resolver blocks
        $this->sql = preg_replace_callback(
            self::V2_RESOLVER_BLOCK_REGEX,
            function( $matches ) use ( $modifier ) {
                $output = call_user_func(
                    $modifier,
                    new Json( $matches[1] )
                );
                return sprintf(
                    "/** resolver\n%s\n*/",
                    $output->getPretty()
                );
            },
            $this->sql
        );
        return $this;
    }

}

/*

function resolverBlockConsistencyCheck ( Json $input ) {

    $options = $input->get();
    if( !isset( $options['depends'] ) ) {
        $options['depends'] = [];
    }
    // replace "app", dependency with "bond" dependency with
    if( false !== $key = array_search( "app", $options['depends'] ) ) {
        $options['depends'][$key] = 'bond';
    }
    sort( $options['depends'] );
    // set the searchPath if it doesn't exist and we need access to a extension
    if( in_array( 'citext', $options['depends'] ) and !isset( $options['searchPath'] ) ) {
        $options['searchPath'] = 'extensions';
    }
    if( isset( $options['searchPath'] ) ) {
        $options['searchPath'] = str_replace( "app", "bond", $options['searchPath'] );
    }

    return Json::makeFromObject( $options );

}

*/