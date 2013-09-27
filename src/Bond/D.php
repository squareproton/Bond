<?php

namespace Bond {

    use ref;

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
        public function __construct( $host, $channel, array $phpRefOptions = array() )
        {
            $this->host = (string) $host;
            $this->setChannel($channel);
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

        public function setChannel( $channel )
        {
            $this->channel = ltrim( (string) $channel, '/' );
            return $this;
        }

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

        /**
         * Debug a arbritary number of objects
         *
         * @param mixed Item to debug
         * @param ...
         */
        public function __invoke()
        {

            $trace = $this->formatTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
            $originalRefOptions = $this->setRefConfig($this->phpRefOptions);
            $ref = new ref('html');

            foreach( func_get_args() as $arg ) {

                ob_start();
                $ref->query( $arg, null );
                $html = ob_get_clean();

                $this->makeUberRequest(
                    array(
                        'handler' => 'php-ref',
                        'args' => array(
                            $html,
                            $trace,
                        )
                    )
                );

            }

            $this->setPhpRefOptions($originalRefOptions);

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

            $this->uberRequest(
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

        private function makeUberRequest( $data )
        {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->getRequestUrl() );
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json'] );
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data) );

            $response = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);

            // have any problems
            if( $response === false ) {
                throw new \Exception("Unable to connect to debugger as `{$this->url}`");
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

}