<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// setup a weird namespace for some testing
namespace Some\Weird\WonderFull {

    class stdclass extends \stdclass {}

}

namespace Bond\Tests {

    use stdclass;

    function extractTagsCallback()
    {
        return range(1,10);
    }

    class extractTagsObject
    {
        public $key;
        public $value;
        public $ref;
        public $type;

        function __construct($key, $value, $ref = null, $type = '@' )
        {
            $this->value = $value;
            $this->key = $key;
            $this->ref = $ref;
            $this->type = $type;
        }

        function getComment()
        {
            return "{$this->type}{$this->key}: {$this->value}";
        }

        function getReferences()
        {
            return $this->ref;
        }

    }

    // the main bulk of our testing
    class FunctionsTest extends \PHPUnit_Framework_Testcase
    {

        public function testArrayKeysExist()
        {

            $numeric = [1,2,3];
            $snacks = array(
                "fish" => 'cod',
                "chips" => 'crispy',
                "salt" => true,
                "vinegar" => false,
                "pickedOnion" => null,
            );

            $this->assertTrue(
                \Bond\array_keys_exist( 'fish', 'chips', 'salt', 'vinegar', 'pickedOnion', $snacks )
            );

            $this->assertTrue(
                \Bond\array_keys_exist( 'fish', 'pickedOnion', $snacks )
            );

            $this->assertTrue(
                \Bond\array_keys_exist( 'fish', 'fish', $snacks )
            );

            $this->assertTrue(
                \Bond\array_keys_exist( 'fish', 'fish', ['chips'], $snacks )
            );

            $this->assertTrue(
                \Bond\array_keys_exist( array_keys( $snacks ), $snacks )
            );

            $this->assertFalse(
                \Bond\array_keys_exist( ['nope'], $snacks )
            );

            $this->assertFalse(
                \Bond\array_keys_exist( 'fuck', $snacks )
            );

        }

        public function testNullify()
        {
            $value = new stdClass();
            $this->assertSame( $value, \Bond\nullify($value) );

            $value = 'spanner';
            $this->assertSame( $value, \Bond\nullify($value) );

            $value = ' !';
            $this->assertSame( $value, \Bond\nullify($value) );

            $value = ' ';
            $this->assertNull( \Bond\nullify($value) );

            $value = '';
            $this->assertNull( \Bond\nullify($value) );

            $value = null;
            $this->assertNull( \Bond\nullify($value) );

        }

        public function testBoolval()
        {

            $this->assertTrue( \Bond\boolval( true ) );
            $this->assertTrue( \Bond\boolval( 1 ) );
            $this->assertTrue( \Bond\boolval( 'true' ) );
            $this->assertTrue( \Bond\boolval( 'TRUE' ) );
            $this->assertTrue( \Bond\boolval( 'True' ) );
            $this->assertTrue( \Bond\boolval( 'tTuE' ) );
            $this->assertTrue( \Bond\boolval( 'spanner' ) );
            $this->assertTrue( \Bond\boolval( 't' ) );
            $this->assertTrue( \Bond\boolval( 'T' ) );

            $this->assertFalse( \Bond\boolval( false ) );
            $this->assertFalse( \Bond\boolval( null ) );
            $this->assertFalse( \Bond\boolval( 0 ) );
            $this->assertFalse( \Bond\boolval( 'false' ) );
            $this->assertFalse( \Bond\boolval( 'fAlSE' ) );
            $this->assertFalse( \Bond\boolval( 'False' ) );
            $this->assertFalse( \Bond\boolval( 'f' ) );
            $this->assertFalse( \Bond\boolval( 'F' ) );

        }

        public function testIsIntIsh()
        {
            $this->assertFalse( \Bond\is_intish( "1.0" ) );
            $this->assertTrue( \Bond\is_intish( 1 ) );
            $this->assertTrue( \Bond\is_intish( "1" ) );
            $this->assertFalse( \Bond\is_intish( " 1" ) );
            $this->assertFalse( \Bond\is_intish( null ) );
            $this->assertFalse( \Bond\is_intish( "" ) );
        }

        public function testgetUnQualifiedClass()
        {
            $this->assertSame( \Bond\get_unqualified_class( "Bond\RecordManager"), "RecordManager" );
            $this->assertSame( \Bond\get_unqualified_class( "RecordManager"), "RecordManager" );
            $this->assertSame( \Bond\get_unqualified_class( new \Some\Weird\WonderFull\stdclass() ), "stdclass" );
            $this->assertSame( \Bond\get_unqualified_class( new stdClass() ), "stdClass" );
        }

        public function testgetNamespace()
        {
            $this->assertSame( \Bond\get_namespace( "Bond\RecordManager"), "Bond" );
            $this->assertSame( \Bond\get_namespace( "RecordManager"), "" );
            $this->assertSame( \Bond\get_namespace( new \Some\Weird\WonderFull\stdclass() ), 'Some\Weird\WonderFull' );
            $this->assertSame( \Bond\get_namespace( new stdClass() ), "" );
        }

        public function testExtractTags()
        {

            $this->assertSame( \Bond\extract_tags( "spanner: monkey"), array() );
            $this->assertSame( \Bond\extract_tags( "@spanner: monkey"), array( 'spanner' => 'monkey' ) );
            $this->assertSame( \Bond\extract_tags( "%spanner: \"monkey\"" ), array( 'spanner' => 'monkey' ) );
            $this->assertSame( \Bond\extract_tags( "%spanner.spoon: \"monkey\"" ), array( 'spanner' => array( 'spoon' => 'monkey' ) ) );
            $this->assertSame( \Bond\extract_tags( "%spanner.spoon.goat: \"monkey\"" ), array( 'spanner' => array( 'spoon' => array('goat' => 'monkey' ) ) ) );

            $this->assertSame( \Bond\extract_tags( "%spanner.spoon.goat" ), array( 'spanner' => array( 'spoon' => array('goat' => true ) ) ) );
            $this->assertSame( \Bond\extract_tags( "@spanner: true" ), array( 'spanner' => true ) );
            $this->assertSame( \Bond\extract_tags( "@spanner: t" ), array( 'spanner' => true ) );
            $this->assertSame( \Bond\extract_tags( "@spanner: f" ), array( 'spanner' => false ) );

            $this->assertSame( \Bond\extract_tags( "@spanner: f\n@spanner: true" ), array( 'spanner' => array( false, true ) ) );
            $this->assertSame( \Bond\extract_tags( "@form.hide\n@form.label: Contact" ), array( 'form' => array( 'hide' => true, 'label' => 'Contact' ) ) );

        }

        public function testExtractTagsWithArray()
        {

            $this->assertSame( \Bond\extract_tags( "@nested[a.b]: true" ), array( 'nested' => array( 'a.b' => true ) ) );
            $this->assertSame( \Bond\extract_tags( "@nested[]: true\n@nested[]: false" ), array( 'nested' => array( true, false ) ) );

        }

        public function testExtractTagsWithPrefix()
        {
            $this->assertSame( \Bond\extract_tags( "%spanner.spoon.goat: \"monkey\"", 'spanner' ), array( 'spoon' => array('goat' => 'monkey' ) ) );
            $this->assertSame( \Bond\extract_tags( "%spanner.spoon.goat: \"monkey\"", 'moon' ), array() );
        }

        public function testExtractTagsWithCallback()
        {
            $this->assertSame( \Bond\extract_tags( '$form: \\Bond\\Tests\\extractTagsCallback'), array( 'form' => extractTagsCallback() ) );
            $this->assertSame( \Bond\extract_tags( '$form: '.__CLASS__.'::extractTagsCallback'), array( 'form' => self::extractTagsCallback() ) );
        }

        public function testExtractTagsObject()
        {
            $key = 'form';
            $value = 'sometagtext';
            $obj = new extractTagsObject($key,$value);

            $this->assertSame( \Bond\extract_tags($obj), array( $key => $value ) );
        }

    //    public function testExtractTagsInheritance()
    //    {
    //        $key = 'form';
    //        $value = 'sometagtext';
    //        $obj0 = new extractTagsObject( $key, $value );
    //        $obj1 = new extractTagsObject( $key, '@inherit', $obj0 );
    //        $obj2 = new extractTagsObject( $key, '@inherit', $obj1 );
    //
    //        $this->assertSame( \Bond\extract_tags($obj2), array( $key => $value ) );
    //    }
    //
    //    public function testExtractTagsInheritanceNamespaced()
    //    {
    //        $key = 'form.one.two';
    //        $value = 'sometagtext';
    //        $obj0 = new extractTagsObject( $key, $value );
    //        $obj1 = new extractTagsObject( $key, '@inherit', $obj0 );
    //        $obj2 = new extractTagsObject( $key, '@inherit', $obj1 );
    //
    //        $this->assertSame( \Bond\extract_tags($obj2), array( 'form' => array( 'one' => array( 'two' => $value ) ) ) );
    //    }
    //
    //    public function testExtractTagsNothingToInherit()
    //    {
    //        $key = 'form.one.two';
    //        $value = 'sometagtext';
    //        $obj0 = new extractTagsObject( 'notaghere', $value );
    //        $obj1 = new extractTagsObject( $key, '@inherit', $obj0 );
    //        $obj2 = new extractTagsObject( $key, '@inherit', $obj1 );
    //
    //        $this->assertSame( \Bond\extract_tags($obj2), array() );
    //    }
    //
    //    public function testExtractTagsDontInheritTooMuch()
    //    {
    //
    //        $key = 'form.one';
    //        $value = 'sometagtext';
    //        $obj0 = new extractTagsObject( 'form', '{"one":"one","two":"two","three":"three"}', null, '%' );
    //        $obj1 = new extractTagsObject( $key, '@inherit', $obj0 );
    //        $obj2 = new extractTagsObject( $key, '@inherit', $obj1 );
    //
    //        $this->assertSame( \Bond\extract_tags($obj2), array( "form" => array( "one" => "one" ) ) );
    //
    //    }

        public static function extractTagsCallback()
        {
            return range(1,20);
        }

        public function testMixedCase()
        {
            $this->assertSame( \Bond\mixed_case('name'), "name" );
            $this->assertSame( \Bond\mixed_case('codeMonkey'), "codeMonkey" );
            $this->assertSame( \Bond\mixed_case('Code Monkey'), "codeMonkey" );
            $this->assertSame( \Bond\mixed_case('  Code Monkey'), "codeMonkey" );
            $this->assertSame( \Bond\mixed_case('code_Monkey'), "codeMonkey" );
            $this->assertSame( \Bond\mixed_case('code_monkey'), "codeMonkey" );
            $this->assertSame( \Bond\mixed_case('_code_monkey'), "codeMonkey" );
            $this->assertSame( \Bond\mixed_case('ID'), "ID" );
            $this->assertSame( \Bond\mixed_case('Id'), "id" );
            $this->assertSame( \Bond\mixed_case(''), "" );
            $this->assertSame( \Bond\mixed_case(' '), "" );
        }

        public function testPascalCase()
        {
            $this->assertSame( \Bond\pascal_case('name'), "Name" );
            $this->assertSame( \Bond\pascal_case('codeMonkey'), "CodeMonkey" );
            $this->assertSame( \Bond\pascal_case('code_Monkey'), "CodeMonkey" );
            $this->assertSame( \Bond\pascal_case('code_monkey'), "CodeMonkey" );
            $this->assertSame( \Bond\pascal_case('_code_monkey'), "CodeMonkey" );
            $this->assertSame( \Bond\pascal_case('ID'), "ID" );
            $this->assertSame( \Bond\pascal_case('Id'), "Id" );
        }

        /*
         * @author Joseph
         */
        public function testUnderscoreCase()
        {
            $this->assertSame ( "", \Bond\underscore_case("") );
            $this->assertSame ( "name", \Bond\underscore_case("name") );
            $this->assertSame ( "name", \Bond\underscore_case("Name") );
            $this->assertSame ( "code_monkey", \Bond\underscore_case("codeMonkey") );
            $this->assertSame ( "code_monkey", \Bond\underscore_case("CodeMonkey") );
            $this->assertSame ( "code_monkey", \Bond\underscore_case("code_monkey") );
            $this->assertSame ( "code_monkey", \Bond\underscore_case("code_Monkey") );
            $this->assertSame ( "code_monkey", \Bond\underscore_case("_Code_Monkey") );

        }

    }

}