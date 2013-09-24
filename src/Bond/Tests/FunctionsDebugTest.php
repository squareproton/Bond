<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Tests;

// the main bulk of our testing
class FunctionsDebugTest extends \PHPUnit_Framework_Testcase
{

    public function testFunctionsDebug()
    {

        $obj = [1, "one"];
        $obj[2] =& $obj;

//        d_clear();

        d_sh( <<<SQL
            SELECT
                *
            FROM
                something
SQL
        );
        d($obj);

    }

}
