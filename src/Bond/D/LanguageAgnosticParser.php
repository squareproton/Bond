<?php

namespace Bond\D;

use RFormatter;

class LanguageAgnosticParser
{

    private $fmt;

    public function __construct( RFormatter $formatter )
    {
        $this->fmt = $formatter;
    }

    public function query($subject, $expression = null)
    {
        ob_start();

        $this->fmt->startRoot();
        $this->fmt->startExp();
        $this->evaluateExp($expression);
        $this->fmt->endExp();
        $this->evaluate($subject);
        $this->fmt->endRoot();
        $this->fmt->flush();

        return ob_get_clean();
    }

    public function evaluate($subject)
    {
        if( is_null($subject) ) {
            return $this->fmt->text('null');
        }

        if( is_array($subject) ) {

            // is scalar
            if( isset($subject['scalar']) ) {
                $value = $subject['scalar'];

                if( is_bool($subject['scalar']) ) {
                    $value = $value ? 'true' : 'false';
                    return $this->fmt->text($value, $value, 'bool');
                }
                if( is_numeric($value) ) {
                    $type = gettype($value);
                    return $this->fmt->text($type, $value, $type);
                }

                if( is_string($value) ) {
                    $length   = strlen($value);
                    $encoding = mb_detect_encoding($value);
                    $info     = $encoding && ($encoding !== 'ASCII') ? $length . '; ' . $encoding : $length;
                    $this->fmt->text('string', $value, "string({$info})");
                }
            }

            if( isset($subject['class'] ) ) {

                $this->fmt->startContain('class');
                $this->fromReflector($subject);
                $this->fmt->text('object', ' object');
                $this->fmt->endContain();

                $hasProperties = isset($subject['properties']) && count($subject['properties']);
                $hasMethods = isset($subject['methods']) and $subject['methods'];

                if( !$hasProperties and !$hasMethods ) {
                    $this->$this->fmt->emptyGroup();
                    return;
                }

                $this->fmt->startGroup();

                if( $hasProperties ) {
                    $this->fmt->sectionTitle('Properties');
                }

                if( $hasMethods ) {
                    $this->fmt->sectionTitle('Methods');

                    foreach( $subject['methods'] as $method => $args ) {

                        $this->fmt->startRow();
//                        $this->fmt->sep($method->isStatic() ? '::' : '->');
                        $this->fmt->sep('->');

                        $this->fmt->colDiv();
                        $bubbles = [];
                        if(true) {
                            $bubbles[] = array('P', 'Protected');
                        }
                        $this->fmt->bubbles($bubbles);
                        $this->fmt->colDiv(4 - count($bubbles));
//                        $this->fmt->startContain($type);

                        $this->fmt->text('name', $method, null );

                        if( isset($args ) ) {
                            foreach( $args as $arg ) {

                            }
                        }
                        if( !$args ) {
                            foreach( $args as $arg ) {
                                if( $args)
                            }

                      // foreach($method->getParameters() as $idx => $parameter){
                      //   $meta      = null;
                      //   $paramName = "\${$parameter->name}";
                      //   $optional  = $parameter->isOptional();
                      //   if($parameter->isPassedByReference())
                      //     $paramName = "&{$paramName}";
                      //   $type = array('param');
                      //   if($optional)
                      //     $type[] = 'optional';
                      //   $this->fmt->startContain($type);
                      //   // attempt to build meta
                      //   foreach($paramCom as $tag){
                      //     list($pcTypes, $pcName, $pcDescription) = $tag;
                      //     if($pcName !== $paramName)
                      //       continue;
                      //     $meta = array('title' => $pcDescription);
                      //     if($pcTypes)
                      //       $meta['left'] = $pcTypes;
                      //     break;
                      //   }
                      //   try{
                      //     $paramClass = $parameter->getClass();
                      //   }catch(\Exception $e){
                      //     // @see https://bugs.php.net/bug.php?id=32177&edit=1
                      //   }
                      //   if($paramClass){
                      //     $this->fmt->startContain('hint');
                      //     $this->fromReflector($paramClass, $paramClass->name);
                      //     $this->fmt->endContain();
                      //     $this->fmt->sep(' ');
                      //   }
                      //   if($parameter->isArray()){
                      //     $this->fmt->text('hint', 'array');
                      //     $this->fmt->sep(' ');
                      //   }
                      //   $this->fmt->text('name', $paramName, $meta);
                      //   if($optional){
                      //     $paramValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                      //     $this->fmt->sep(' = ');
                      //     if($this->env['is546'] && !$parameter->getDeclaringFunction()->isInternal() && $parameter->isDefaultValueConstant()){
                      //       $this->fmt->text('constant', $parameter->getDefaultValueConstantName(), 'Constant');
                      //     }else{
                      //       $this->evaluate($paramValue, true);
                      //     }
                      //   }
                      //   $this->fmt->endContain();
                      //   if($idx < $paramCount - 1)
                      //     $this->fmt->sep(', ');
                      // }

                        }

                        $this->fmt->sep('(');
                        $this->fmt->sep(')');
//                        $this->fmt->endContain();
                        $this->fmt->endRow();


                    }

                    /*
                    foreach($methods as $idx => $method){
                      $this->fmt->startRow();
                      $this->fmt->sep($method->isStatic() ? '::' : '->');
                      $this->fmt->colDiv();
                      $bubbles = array();
                      if($method->isAbstract())
                        $bubbles[] = array('A', 'Abstract');
                      if($method->isFinal())
                        $bubbles[] = array('F', 'Final');
                      if($method->isProtected())
                        $bubbles[] = array('P', 'Protected');
                      if($method->isPrivate())
                        $bubbles[] = array('!', 'Private');
                      $this->fmt->bubbles($bubbles);
                      $this->fmt->colDiv(4 - count($bubbles));
                      // is this method inherited?
                      $inherited = $reflector->getShortName() !== $method->getDeclaringClass()->getShortName();
                      $type = array('method');
                      if($inherited)
                        $type[] = 'inherited';
                      if($method->isPrivate())
                        $type[] = 'private';
                      $this->fmt->startContain($type);
                      $name = $method->name;
                      if($method->returnsReference())
                        $name = "&{$name}";
                      $this->fromReflector($method, $name, $reflector);
                      $paramCom   = $method->isInternal() ? array() : static::parseComment($method->getDocComment(), 'tags');
                      $paramCom   = empty($paramCom['param']) ? array() : $paramCom['param'];
                      $paramCount = $method->getNumberOfParameters();
                      $this->fmt->sep('(');
                      // process arguments
                      foreach($method->getParameters() as $idx => $parameter){
                        $meta      = null;
                        $paramName = "\${$parameter->name}";
                        $optional  = $parameter->isOptional();
                        if($parameter->isPassedByReference())
                          $paramName = "&{$paramName}";
                        $type = array('param');
                        if($optional)
                          $type[] = 'optional';
                        $this->fmt->startContain($type);
                        // attempt to build meta
                        foreach($paramCom as $tag){
                          list($pcTypes, $pcName, $pcDescription) = $tag;
                          if($pcName !== $paramName)
                            continue;
                          $meta = array('title' => $pcDescription);
                          if($pcTypes)
                            $meta['left'] = $pcTypes;
                          break;
                        }
                        try{
                          $paramClass = $parameter->getClass();
                        }catch(\Exception $e){
                          // @see https://bugs.php.net/bug.php?id=32177&edit=1
                        }
                        if($paramClass){
                          $this->fmt->startContain('hint');
                          $this->fromReflector($paramClass, $paramClass->name);
                          $this->fmt->endContain();
                          $this->fmt->sep(' ');
                        }
                        if($parameter->isArray()){
                          $this->fmt->text('hint', 'array');
                          $this->fmt->sep(' ');
                        }
                        $this->fmt->text('name', $paramName, $meta);
                        if($optional){
                          $paramValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                          $this->fmt->sep(' = ');
                          if($this->env['is546'] && !$parameter->getDeclaringFunction()->isInternal() && $parameter->isDefaultValueConstant()){
                            $this->fmt->text('constant', $parameter->getDefaultValueConstantName(), 'Constant');
                          }else{
                            $this->evaluate($paramValue, true);
                          }
                        }
                        $this->fmt->endContain();
                        if($idx < $paramCount - 1)
                          $this->fmt->sep(', ');
                      }
                      $this->fmt->sep(')');
                      $this->fmt->endContain();
                      $this->fmt->endRow();
                    }
                    */


                }



                $this->fmt->endGroup();
            }

        }

    }

    public function fromReflector($subject)
    {
        $bubbles[] = array('C', 'Cloneable');
        $this->fmt->bubbles($bubbles);
        $this->fmt->text('name', $subject['class'][0], null );

    }

    public function evaluateExp()
    {
    }

}
