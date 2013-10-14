<?php

namespace Bond;

use ref;
use Bond\D\RHtmlSpanFormatter;

/**
 * PHP counterpart for uberdebug
 */
class D
{

    /**
     * @var string Hostname of the uberdebugging server. Think, 'localhost' or '192.168.2.17'
     */
    private $host;

    /**
     * @var string Non empty string of the channel you wish to post to
     */
    private $channel;

    /**
     * @var string Apikey to use with a debug channel account. Optional.
     */
    private $apiKey;

    /**
     * See, ref.php for the complete list of allowed options
     * The ones I think you'll probably want are
     * array(
     *     'expLvl'               => 1, // initially expanded levels (for HTML mode only)
     *     'maxDepth'             => 6, // depth limit (0 = no limit)
     *     'showIteratorContents' => false,
     *     'showResourceInfo'     => true,
     *     'showMethods'          => true,
     *     'showPrivateMembers'   => false,
     *     'showStringMatches'    => true, // peform string matches (date, file, functions, classes, json, serialized data, regex etc.). Thisseriously slows down queries on large amounts of data
     * );
     */
    private $phpRefOptions;

    /**
     * Standard constructor, blah blah
     * @param string Hostname
     * @param string Channel
     * @param array ref options. See, ref.php for list of allowed options
     */
    public function __construct( $host, $channel, $apiKey = null, array $phpRefOptions = array() )
    {
        $this->host = (string) $host;
        $this->setChannel($channel);
        if( null !== $apiKey and !is_string($apiKey) ) {
            throw new \InvalidArgumentException("apiKey must be a string.");
        }
        $this->apiKey = $apiKey;
        $this->setPhpRefOptions($phpRefOptions);
    }

    /**
     * Magic getter.
     * @param string propertyName
     * @return mixed
     */
    public function __get( $property )
    {
        if( property_exists( $this, $property ) ) {
            return $this->$property;
        }
        throw new \InvalidArgumentException("Unknown property `{$property}`.");
    }

    /**
     * Set the channel you with to subscribe to
     * @param string Channel use use
     * @return Bond\D
     */
    public function setChannel( $channel )
    {
        $this->channel = ltrim( (string) $channel, '/' );
        return $this;
    }

    /**
     * Set phpref options that will be used by this instance of D
     * @param array
     * @return Bond\D
     */
    public function setPhpRefOptions( array $phpRefOptions )
    {
        if( !array_key_exists('stylePath', $phpRefOptions) ) {
            $phpRefOptions['stylePath'] = false;
        }
        if( !array_key_exists('scriptPath', $phpRefOptions) ) {
            $phpRefOptions['scriptPath'] = false;
        }
        $this->phpRefOptions = $phpRefOptions;
        return $this;
    }

    /**
     * Get the debug request url
     * @return string The url where the debugger can be accessed from
     */
    public function getRequestUrl()
    {
        return "http://{$this->host}:1025/{$this->channel}";
    }

    /**
     * Clears the uberdebug window
     */
    public function clear()
    {
        $this->makeUberRequest(
            array(
                'handler' => 'clear',
                'args' => array()
            )
        );
    }

    public function __invoke()
    {
        return call_user_func_array([$this, "log"], func_get_args());
    }

    /**
     * Debug a arbritary number of objects
     *
     * @param mixed Item to debug
     * @param ...
     */
    public function log()
    {

        $trace = $this->formatTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $originalRefOptions = $this->setRefConfig($this->phpRefOptions);

        // use the custom formatter which doesn't have the "multiple levels of nesting break out of their container' bug
        $ref = new ref(new RHtmlSpanFormatter());

        foreach( func_get_args() as $arg ) {

            ob_start();
            $ref->query( $arg, null );
            $html = ob_get_clean();

            $this->makeUberRequest(
                array(
                    'handler' => 'php-ref',
                    'args' => array(
                        $html,
                        $trace
                    )
                )
            );

        }

        $this->setRefConfig($originalRefOptions);

    }

    /**
     * Syntax highlight a string
     *
     * @param string Text to highlight
     * @param string Language to highlight it as
     * @param bool Deindent string? This works well for sql
     */
    public function syntaxHighlight( $text, $lang = 'sql', $deIndent = true )
    {

        if( $deIndent ) {
            $text = $this->deIndent($text);
        }

        $trace = $this->formatTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->makeUberRequest(
            array(
                'handler' => 'syntaxHighlight',
                'args' => array(
                    $text,
                    $lang,
                    $trace,
                )
            )
        );

    }

    public function makeUberRequest( $data )
    {

        // add apiKey to request if set
        if( null !== $this->apiKey ) {
            $data['apiKey'] = (string) $this->apiKey;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url = $this->getRequestUrl() );
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json'] );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data) );

        $response = curl_exec($ch);
        $curlInfo = curl_getinfo($ch);

        // have any problems
        if( $response === false ) {
            throw new \Exception("Unable to connect to debugger as `{$url}`");
        } elseif ( $curlInfo['http_code'] !== 200 ) {
            throw new \Exception($response);
        }

        return $curlInfo;

    }

    private function setRefConfig( array $options )
    {
        $output = array();
        foreach( $options as $option => $value ) {
            $output[$option] = ref::config($option);
            ref::config($option, $value);
        }
        return $output;
    }

    private function formatTrace( $trace )
    {
        return array_map(
            function ( $component ) {
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
    }

    private function deIndent( $text )
    {
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
        return implode("\n", $text);
    }

}