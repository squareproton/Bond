<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Exception\BadTypeException;

/**
 * Perform various formatting operations on a string.
 */
class Format
{

    private $lines;
    private $newlineSeparator;
    private $indentChar;

    /**
     * Format a string indent etc
     * @param input either a string or a array
     * @param newlineSeparator
     * @param indentChar
     */
    public function __construct( $stringOrArray = [], $newlineSeparator = "\n", $indentChar = ' ' )
    {

        if( is_array( $stringOrArray ) ) {
            $this->lines = $stringOrArray;
        } else {
            $this->lines = explode( $newlineSeparator, (string) $stringOrArray );
        }

        $this->newlineSeparator = $newlineSeparator;
        $this->indentChar = $indentChar;

    }

    /**
     * @return string
     */
    public function __toString()
    {
        return implode( $this->newlineSeparator, $this->lines );
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->lines;
    }

    /**
     * Get the number of chars
     * @return int
     */
    public function getIndent()
    {
        $leadingWhitespace = array();
        $n = 0;
        foreach( $this->lines as $line ) {
            if( !empty( $line ) ) {
                $leadingWhitespace[$n] = strlen( $line ) - strlen( ltrim( $line ) );
            }
            $n++;
        }
        return min( $leadingWhitespace );
    }

    /**
     * Completely deindent a string
     * @return Bond\Format
     */
    public function deIndent()
    {
        $ltrim = $this->getIndent();
        if( $ltrim > 0 ) {
            foreach( $this->lines as &$line ) {
                $line = substr( $line, $ltrim );
            }
        }
        return $this;
    }

    /**
     * Indent a string
     * @param int|string Either the length of the indentation in whitespace or the string to indent
     * @return Bond\Format
     */
    public function indent( $indent = 4 )
    {
        $indent = $this->generateIndent( $indent );
        foreach( $this->lines as &$line ) {
            $line = $indent . $line;
        }
        return $this;
    }

    /**
     * Indent a string to a specified length
     * @return Bond\Format
     */
    public function indentTo( $indent )
    {
        return $this->deIndent()->indent( $indent );
    }

    /**
     * Comment out a string
     * @param string $comment
     * @return Bond\Format
     */
    public function comment( $comment = '#' )
    {
        foreach( $this->lines as &$line ) {
            $line = $comment . $line;
        }
        return $this;
    }

    /**
     * Uncomment string
     * @param string $comment you are removing
     * @return Bond\Format
     */
    public function uncomment($comment = '#')
    {
        $commentLength = strlen($comment);
        foreach( $this->lines as &$line ) {
            if( 0 === strpos($line, $comment) ) {
                $line = substr( $line, $commentLength );
            }
        }
        return $this;
    }

    /**
     * Remove trailing whitespace for each line
     * @return Bond\Format
     */
    public function rtrim()
    {
        foreach( $this->lines as &$line ) {
            $line = rtrim( $line );
        }
        return $this;
    }

    /**
     * Make docblock this string
     * @return Bond\Format
     */
    public function docblockify()
    {
        foreach( $this->lines as &$line ) {
            $line = ' * ' . $line;
        }
        array_unshift( $this->lines, '/**' );
        array_push( $this->lines, ' */');
        return $this;
    }

    /**
     * Duplicate empty lines remove
     * @return Bond\Format
     */
    public function removeDuplicateEmptyLines()
    {
        $output = [];
        $c = 0;
        foreach( $this->lines as $key => $line ) {
            if( strlen(rtrim($line)) === 0 ) {
                $c++;
                if( $c === 1 ) {
                    $output[] = $line;
                }
            } else {
                $c = 0;
                $output[] = $line;
            }
        }
        $this->lines = $output;
        return $this;
    }

    /**
     * Add line numbers to output. Useful for debugging output. This is obviously destructive
     * @return Bond\Format
     */
    public function addLineNumbers()
    {
        $lengthToPad = strlen(count($this->lines));
        $c = 0;
        foreach( $this->lines as &$line ) {
            $c++;
            $line = sprintf(
                "%s %s",
                str_pad( (string) $c, $lengthToPad, ' ', STR_PAD_LEFT ),
                $line
            );
        }
        return $this;
    }

    /**
     * Generate indention string from length.
     * @param int|string Either the length of the indentation in whitespace or the string to indent
     * @return string Indention string
     */
    private function generateIndent( $indent )
    {
        if( is_integer( $indent ) ) {
            $indent = str_repeat( $this->indentChar, $indent );
        } elseif( !is_string($indent) ) {
            throw new BadTypeException( $indent, 'int|string' );
        }
        return $indent;
    }

}