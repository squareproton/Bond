<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\MagicGetter;
use Bond\Exception\BadPhpException;

class Php
{

    use MagicGetter;

    private $php;

    /**
     * @param string $php
     * @param bool Insert php tags
     */
    public function __construct( $php, $autoInsertPhpTags = false )
    {
        $php = trim( (string) $php );

        // automatically insert php tags if we don't have them
        if( $autoInsertPhpTags and !preg_match( '/^<\\?/', $php ) ) {
            $this->php = "<?php\n".$php."\n";
        } else {
            $this->php = $php;
        }
    }

    /**
     * Run Php through lint checker
     * @retrun bool Is the passed php valid
     */
    public function isValid( &$errors = null, $throwException = false )
    {

        // open pipe
        $descriptorSpec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $process = proc_open('php -l', $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new \Exception('Could not open PHP');
        }
        fwrite($pipes[0], $this->php);
        fclose($pipes[0]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $returnValue = proc_close($process);

        if( $throwException && $returnValue !== 0 ) {
            throw new BadPhpException($errors);
        }

        return $returnValue === 0;

    }

    /**
     * Return a string which is a php-parsable representation of a variable
     * @param mixed $var
     * @param int $indent
     * @param bool $dontIndentFirstLine
     * @return string
     */
    public static function varExport( $var, $indent = 0, $dontIndentFirstLine = true )
    {

        $export = var_export( $var, true );

        // array ( => array (
        $export = preg_replace( '/^array \\(/', 'array(', $export );

        // UPPERCASE null(s) seem to bother me for some reason. I've no idea why.
        if( $export === 'NULL' ) {
            $export = 'null';
        }

        //
        // change the default indent from 2 to 4
        $export = str_replace( '  ', '    ', $export );

        $lines = explode( "\n", $export );

        $i = $dontIndentFirstLine ? 1 : 0;
        $indent = \str_repeat( ' ', $indent );
        for( ; $i<count($lines); $i++) {
            $lines[$i] = $indent.$lines[$i];
        }

        return implode( "\n", $lines );

    }

}