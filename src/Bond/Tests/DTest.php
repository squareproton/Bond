<?php

namespace Bond\Tests;

use Bond\D;

// class DTest extends \Bond\Normality\Tests\NormalityProvider
class DTest extends \PHPUnit_Framework_Testcase
{
    public function testSomething()
    {

        $d = new D(
            '192.168.2.17',
            'unittest',
            [
                'showMethods' => false,
                'showPrivateMembers' => true,
                'expLvl' => 2
            ]
        );

//        $d->clear();

        $d( $this );

        $d( [0 => [ 1 => [ 2=> [ 3 => []]]]] ); // first time php-ref has this bug

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