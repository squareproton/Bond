<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace {

    use \ref;

    /**
     * Run something through d_sh and pipe to
     */
    function d () {

        // get a backtace and format it prettily
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $trace = array_map(
            function ($component) {

                if( isset($component['file'], $component['line']) and $component['line'] > 0 ) {
                    $location = sprintf( "%s(%s): ", $component['file'], $component['line'] );
                } else {
                    $location = '';
                }

                $fn = isset( $component['class'] ) ? "{$component['class']}{$component['type']}" : '';
                $fn .= "{$component['function']}()";

                return array(
                    'location' => $location,
                    'fn' => $fn
                );
            },
            $trace
        );

        $args = func_get_args();
        $options = array();

        $ref = new ref('html');

        // names of the arguments that were passed to this function
        $expressions = ref::getInputExpressions($options);

        // something went wrong while trying to parse the source expressions?
        // if so, silently ignore this part and leave out the expression info
        if( func_num_args() !== count($expressions) ) {
            $expressions = null;
        }

        // record php-ref's original settings for restoration later
        $originalStylePath = ref::config('stylePath');
        $originalScriptPath = ref::config('scriptPath');
        ref::config('stylePath', false);
        ref::config('scriptPath', false);

        foreach( $args as $index => $arg ) {

            ob_start();
            $ref->query( $arg, $expressions ? $expressions[$index] : null );
            $html = ob_get_clean();

            $data = array( 'handler' => 'php-ref' );
            $data['args'][] = $html;
            $data['args'][] = $trace;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, '192.168.2.17:1025/spanner/fishgoat');
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data) );
//            curl_setopt($ch, CURLOPT_HEADER, 'text/html');

            $response = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);

            // have any problems
            if( $response === false ) {
                throw new \Exception("unable to connect to debugger");
            } elseif ( $curlInfo['http_code'] !== 200 ) {
                throw new \Exception($response);
            }

            print_r( $curlInfo );

        }

        // restore php-ref's original settings
        ref::config('stylePath', $originalStylePath);
        ref::config('scriptPath', $originalScriptPath);

        // stop the script if this function was called with the bitwise not operator
        if( in_array('~', $options, true) ) {
            exit(0);
        }

    }

    function d_clear()
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, '192.168.2.17:1025/spanner/fishgoat');
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"handler": "clear", "args": []}');
        $response = curl_exec($ch);

    }

    function d_sh( $text, $lang = 'sql', $deIndent = true )
    {

        if( $deIndent ) {

            $leadingWhitespace = array();
            $text = explode("\n", $text);
            foreach( $text as $line ) {
                if( !empty( $line ) ) {
                    $leadingWhitespace[] = strlen( $line ) - strlen( ltrim( $line ) );
                }
            }
            $indent = min( $leadingWhitespace );
            foreach( $text as &$line ) {
                $line = substr( $line, $indent );
            }
            $text = implode("\n", $text);

        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $trace = array_map(
            function ($component) {

                if( isset($component['file'], $component['line']) and $component['line'] > 0 ) {
                    $location = sprintf( "%s(%s): ", $component['file'], $component['line'] );
                } else {
                    $location = '';
                }

                $fn = isset( $component['class'] ) ? "{$component['class']}{$component['type']}" : '';
                $fn .= "{$component['function']}()";

                return array(
                    'location' => $location,
                    'fn' => $fn
                );
            },
            $trace
        );

        $data = array(
            'handler' => 'shjs',
            'args' => array(
                $text,
                $lang,
                $trace,
            )
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, '192.168.2.17:1025/spanner/fishgoat');
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data) );
        $response = curl_exec($ch);

    }

}