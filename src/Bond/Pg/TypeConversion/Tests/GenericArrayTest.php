<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\TypeConversion\Tests;

use Bond\Pg\TypeConversion\GenericArray;

class GenericArrayTest extends \PHPUnit_Framework_Testcase
{

    public function testPostgressArrayToPHP()
    {

        $examples = array(
            '{1,2,3}' => array('1','2','3'),
            "{'1','2','3'}" => array( '1','2','3' ),
            "{','}" => array(','),
            "{NULL}" => array( null ),
        );

        // text array
        $converter = new GenericArray('text[]');
        foreach( $examples as $postgres => $php ) {
            $this->assertEquals( $converter($postgres), $php );
        }

    }

}
