<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Tests;

use Bond\Debug;

class DebugUnitTest extends Debug
{
    public function __construct()
    {
        call_user_func_array( 'parent::__construct', func_get_args() );
    }
}

class DebugTest extends \PHPUnit_Framework_Testcase
{

    public function setUp()
    {
        Debug::setTemplate(DebugUnitTest::class);
    }

    public function testObjectInstantiation()
    {
        $this->assertTrue( Debug::get() instanceof DebugUnitTest );
    }

}