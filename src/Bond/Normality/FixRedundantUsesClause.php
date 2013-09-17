<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\Exception\BadPhpException;
use Bond\Normality\Exception\FixUsesClauseException;

use Bond\MagicGetter;

class FixRedundantUsesClause
{

    use MagicGetter;

    const USES_REGEX = '/^\\W*(?:\\/\\/|#)?\\W*use\\W*([a-z0-9_\\\\]+)\\W*(as\\W+([a-z0-9_]+))?\\W*;\\W*$/Uim';

    private $file;
    private $errors = [];

    private $originalPhp;
    private $fixedPhp;

    public function __construct( \SplFileInfo $file )
    {
        if( !$file->isFile() ) {
            throw new \LogicException("Please pass a file not a directory.");
        }
        $this->originalPhp = file_get_contents($this->file->getPathname());
        $php = new Php( $this->originalPhp );
        if( !$php->isValid($errors, true) ) {
            throw new BadPhpException($this->originalPhp, $errors);
        }
        $this->file = $file;

        $this->analyse();

    }

    public function fix()
    {
        if( $this->errors ) {
            file_put_contents($this->file->getPathname(), $this->fixedPhp );
            return true;
        }
        return false;
    }

    private function analyse()
    {

        $outputPhp = $this->originalPhp;

        preg_match_all( self::USES_REGEX, $this->originalPhp, $matches, PREG_SET_ORDER );

        foreach( $matches as $match ) {

            // deal with alias'
            $class = isset( $match[3] ) ? $match[3] : $match[1];
            if( false !== $pos = strrpos( $class, '\\' ) ) {
                $class = substr( $class, $pos + 1 );
            }

            $workingPhp = str_replace($match[0]."\n", '', $outputPhp );

            // no match present
            if( false === $pos = strpos( $workingPhp, $class ) ) {
                $outputPhp = $workingPhp;
                $this->errors[] = $match[0];
            }

            // $debug = sprintf( "%s %s\n", $class, $pos === false ? 'nope' : 'yep' );

        }

        if( $this->errors ) {

            // rtrim every line
            $outputPhp = implode( "\n", array_map( 'rtrim', explode( "\n", $outputPhp ) ) );
            $regex = '/\\v{3,}/m';

            $this->fixedPhp = preg_replace( $regex, "\n\n", $outputPhp );

            // check out new output is valid php
            if( !is_php_valid($this->fixedPhp, false, $errors) ) {
                throw new FixUsesClauseException(
                    "Error fixing uses clause",
                    null,
                    BadPhpException($this->fixedPhp, $errors)
                );
            }

        }

    }

}