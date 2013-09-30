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

    // modified version of RHtmlFormatter which doesn't have the nesting bug
    // see RHtmlFormatter

    /**
     * Generates the output in HTML5 format
     */
    class RHtmlSpanFormatter extends \RFormatter{

      public

        /**
         * Actual output
         *
         * @var  string
         */
        $out    = '',

        /**
         * Tracks current nesting level
         *
         * @var  int
         */
        $level  = 0,

        /**
         * Stores tooltip content for all entries
         *
         * To avoid having duplicate tooltip data in the HTML, we generate them once,
         * and use references (the Q index) to pull data when required;
         * this improves performance significantly
         *
         * @var  array
         */
        $tips   = array(),

        /**
         * Used to cache output to speed up processing.
         *
         * Contains hashes as keys and string offsets as values.
         * Cached objects will not be processed again in the same query
         *
         * @var  array
         */
        $cache  = array();



      protected static

        /**
         * Instance counter
         *
         * @var  int
         */
        $counter   = 0,

        /**
         * Tracks style/jscript inclusion state
         *
         * @var  bool
         */
        $didAssets = false;



      public function flush(){
        print $this->out;
        $this->out   = '';
        $this->cache = array();
        $this->tips  = array();
      }


      public function didCache($id){

        if(!isset($this->cache[$id])){
          $this->cache[$id] = array();
          $this->cache[$id][] = strlen($this->out);
          return false;
        }

        if(!isset($this->cache[$id][1])){
          $this->cache[$id][0] = strlen($this->out);
          return false;
        }

        $this->out .= substr($this->out, $this->cache[$id][0], $this->cache[$id][1]);
        return true;
      }

      public function cacheLock($id){
        $this->cache[$id][] = strlen($this->out) - $this->cache[$id][0];
      }


      public function sep($label = ' '){
        $this->out .= $label !== ' ' ? '<i>' . static::escape($label) . '</i>' : $label;
      }

      public function text($type, $text = null, $meta = null, $uri = null){

        if(!is_array($type))
          $type = (array)$type;

        $tip  = '';
        $text = ($text !== null) ? static::escape($text) : static::escape($type[0]);

        if(in_array('special', $type)){
          $text = strtr($text, array(
            "\r" => '<i>\r</i>',     // carriage return
            "\t" => '<i>\t</i>',     // horizontal tab
            "\n" => '<i>\n</i>',     // linefeed (new line)
            "\v" => '<i>\v</i>',     // vertical tab
            "\e" => '<i>\e</i>',     // escape
            "\f" => '<i>\f</i>',     // form feed
            "\0" => '<i>\0</i>',
          ));
        }

        // generate tooltip reference (probably the slowest part of the code ;)
        if($meta !== null){
          $tipIdx = array_search($meta, $this->tips, true);

          if($tipIdx === false)
            $tipIdx = array_push($this->tips, $meta) - 1;

          $tip = ' data-tip="' . $tipIdx . '"';
        }

        // wrap text in a link?
        if($uri !== null)
          $text = '<a href="' . $uri . '" target="_blank">' . $text . '</a>';

        //$this->out .= ($type !== 'name') ? "<span data-{$type}{$tip}>{$text}</span>" : "<span{$tip}>{$text}</span>";

        $typeStr = '';
        foreach($type as $part)
          $typeStr .= " data-{$part}";

        $this->out .= "<span{$typeStr}{$tip}>{$text}</span>";
      }

      public function startContain($type, $label = false){

        if(!is_array($type))
          $type = (array)$type;

        if($label)
          $this->out .= '<br>';

        $typeStr = '';
        foreach($type as $part)
          $typeStr .= " data-{$part}";

        $this->out .= "<span{$typeStr}>";

        if($label)
          $this->out .= "<span data-match>{$type[0]}</span>";
      }

      public function endContain(){
        $this->out .= '</span>';
      }

      public function emptyGroup($prefix = ''){

        if($prefix !== '')
          $prefix = '<span data-gLabel>' . static::escape($prefix) . '</span>';

        $this->out .= "<i>(</i>{$prefix}<i>)</i>";
      }


      public function startGroup($prefix = ''){

        $maxDepth = ref::config('maxDepth');

        if(($maxDepth > 0) && (($this->level + 1) > $maxDepth)){
          $this->emptyGroup('...');
          return false;
        }

        $this->level++;

        $expLvl = ref::config('expLvl');
        $exp = ($expLvl < 0) || (($expLvl > 0) && ($this->level <= $expLvl)) ? ' data-exp' : '';

        if($prefix !== '')
          $prefix = '<span data-gLabel>' . static::escape($prefix) . '</span>';

        $this->out .= "<i>(</i>{$prefix}<span data-toggle{$exp}></span><span data-group><span data-table>";

        return true;
      }

      public function endGroup(){
        $this->out .= '</span></span><i>)</i>';
        $this->level--;
      }

      public function sectionTitle($title){
        $this->out .= "</span><span data-tHead>{$title}</span><span data-table>";
      }

      public function startRow(){
        $this->out .= '<span data-row><span data-cell>';
      }

      public function endRow(){
        $this->out .= '</span></span>';
      }

      public function colDiv($padLen = null){
        $this->out .= '</span><span data-cell>';
      }

      public function bubbles(array $items){

        if(!$items)
          return;

        $this->out .= '<span data-mod>';

        foreach($items as $info)
          $this->out .= $this->text('mod-' . strtolower($info[1]), $info[0], $info[1]);

        $this->out .= '</span>';
      }

      public function startExp(){
        $this->out .= '<span data-input>';
      }

      public function endExp(){
        $this->out .= '</span><span data-output>';
      }

      public function startRoot(){
        $this->out .= '<!-- ref#' . static::$counter++ . ' --><div>' . static::getAssets() . '<div class="ref">';
      }

      public function endRoot(){
        $this->out .= '</span>';

        // process tooltips
        $tipHtml = '';
        foreach($this->tips as $idx => $meta){

          $tip = '';
          if(!is_array($meta))
            $meta = array('title' => $meta);

          $meta += array(
            'title'       => '',
            'left'        => '',
            'description' => '',
            'tags'        => array(),
            'sub'         => array(),
          );

          $meta = static::escape($meta);
          $cols = array();

          if($meta['left'])
            $cols[] = "<span data-cell data-varType>{$meta['left']}</span>";

          $title = $meta['title'] ?       "<span data-title>{$meta['title']}</span>"       : '';
          $desc  = $meta['description'] ? "<span data-desc>{$meta['description']}</span>"  : '';
          $tags  = '';

          foreach($meta['tags'] as $tag => $values){
            foreach($values as $value){
              if($tag === 'param'){
                $value[0] = "{$value[0]} {$value[1]}";
                unset($value[1]);
              }

              $value  = is_array($value) ? implode('</span><span data-cell>', $value) : $value;
              $tags  .= "<span data-row><span data-cell>@{$tag}</span><span data-cell>{$value}</span></span>";
            }
          }

          if($tags)
            $tags = "<span data-table>{$tags}</span>";

          if($title || $desc || $tags)
            $cols[] = "<span data-cell>{$title}{$desc}{$tags}</span>";

          if($cols)
            $tip = '<span data-row>' . implode('', $cols) . '</span>';

          $sub = '';
          foreach($meta['sub'] as $line)
            $sub .= '<span data-row><span data-cell>' . implode('</span><span data-cell>', $line) . '</span></span>';

          if($sub)
            $tip .= "<span data-row><span data-cell data-sub><span data-table>{$sub}</span></span></span>";

          if($tip)
            $this->out .= "<div>{$tip}</div>";
        }

        $this->out .= '</div></div><!-- /ref#' . static::$counter . ' -->';
      }



      /**
       * Get styles and javascript (only generated for the 1st call)
       *
       * @return  string
       */
      public static function getAssets(){

        // first call? include styles and javascript
        if(static::$didAssets)
          return '';

        ob_start();

        if(ref::config('stylePath') !== false){
          ?>
          <style scoped>
            <?php readfile(str_replace('{:dir}', __DIR__, ref::config('stylePath'))); ?>
          </style>
          <?php
        }

        if(ref::config('scriptPath') !== false){
          ?>
          <script>
            <?php readfile(str_replace('{:dir}', __DIR__, ref::config('scriptPath'))); ?>
          </script>
          <?php
        }

        // normalize space and remove comments
        $output = preg_replace('/\s+/', ' ', trim(ob_get_clean()));
        $output = preg_replace('!/\*.*?\*/!s', '', $output);
        $output = preg_replace('/\n\s*\n/', "\n", $output);

        static::$didAssets = true;
        return $output;
      }


      /**
       * Escapes variable for HTML output
       *
       * @param   string|array $var
       * @return  string|array
       */
      protected static function escape($var){
        return is_array($var) ? array_map('static::escape', $var) : htmlspecialchars($var, ENT_QUOTES);
      }

    }

}