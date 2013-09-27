<?php

namespace Bond {

    use ref;

    class D
    {

        private $host;
        private $channel;
        private $phpRefOptions;

        public function __construct( $host, $channel, array $phpRefOptions = array() )
        {
            $this->host = (string) $host;
            $this->setChannel($channel);
            $this->setPhpRefOptions($phpRefOptions);
        }

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

        private function setRefConfig( array $options )
        {
            $output = array();
            foreach( $options as $option => $value ) {
                $output[$option] = ref::config($option);
                ref::config($option, $value);
            }
            return $output;
        }

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

        public function syntaxHighlight( $text, $lang = 'sql', $deIndent = true )
        {

            if( $deIndent ) {
                $text = $this->deIndent($text);
            }

            $this->uberRequest(
                array(
                    'handler' => 'shjs',
                    'args' => array(
                        $text,
                        $lang,
                        $trace,
                    )
                )
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