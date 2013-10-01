<?php

namespace Bond\Tests;

use Bond\D;

// class DTest extends \Bond\Normality\Tests\NormalityProvider
class DTest extends \PHPUnit_Framework_Testcase
{
    public function testSomething()
    {

        d("fuck");

        $d = new D(
            '192.168.2.17',
            'unittest/pete',
            [
                'showMethods' => true,
                'showPrivateMembers' => true,
                'expLvl' => 2
            ]
        );

        $d( $this );
        $d->clear();
        $d( $this );

        $d->syntaxHighlight( <<<SQL
            SELECT
                *
            FROM
                sometable
            WHERE
                something = true
SQL
        );
    }
}