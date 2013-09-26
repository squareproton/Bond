<?php

namespace Bond\Tests;

use Bond\D;

class DTest extends \PHPUnit_Framework_TestCase
{
    public function testSomething()
    {

        $d = new D(
            '192.168.2.17',
            'unittest',
            [
                'showMethods' => false,
                'showPrivateMembers' => true,
                'expLvl' => 3
            ]
        );

        $d->clear();
        $d(array( array( array())));

    }
}