<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Tests;

use Bond\Normality\Php;
use Bond\Normality\PhpClass;
use Bond\Normality\PhpClassComponent\ClassConstant;
use Bond\Normality\PhpClassComponent\FunctionDeclaration;
use Bond\Normality\PhpClassComponent\VariableDeclaration;

class PhpClassTest extends \PHPUnit_Framework_Testcase
{

    public function testSomething()
    {

        $class = new PhpClass( 'Spanner', 'Fish', false );
        $class->addUses('Goat',['Monkey','Bananna']);
        $class->addExtends('Wibble');
        $class->addImplements('FunTime');

        $class->classComponents[] = new FunctionDeclaration('hello', 'public function hello(){ return "hello"; }' );
        $class->classComponents[] = new FunctionDeclaration('goodbye', 'public function goodbye(){ return "goodbye"; }' );
        $class->classComponents[] = new FunctionDeclaration('__construct', 'public function __construct(){}' );
        $class->classComponents[] = new FunctionDeclaration(
            'multiLineDeclaration',
            <<<'PHP'
/**
 * Some multiline comment
 */
public function multiLineDeclaration ()
{
    return array(
        'monkey' => 'goat',
    );
}
PHP
        );

        $class->classComponents[] = new VariableDeclaration('size', 'public $size = "big";');
        $class->classComponents[] = new VariableDeclaration('age', 'public $age;');

        $class->classComponents[] = new ClassConstant('t-rex', 'const T_REX = 100;');

        $php = $class->render();

        $php = new Php( $class->render() );
        $isValid = $php->isValid($error);

//        echo $class->render()->addLineNumbers();

    }

}