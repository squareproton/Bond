<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Converter\Tests;

use Bond\Pg\Result;
use Bond\Sql\Raw;

class ConverterFactoryTest extends \Bond\Pg\Tests\PgProvider
{
    public function testSomething()
    {

        $db = $this->connectionFactory->get('RW');

        $converterFactory = $db->converterFactory;
        $sql = <<<'SQL'
SELECT
    1,
    true,
    ARRAY['\1',2]::text[],
    null::text[]
;
SQL
;

        // file_put_contents('/home/captain/test.sql', $sql);
        // passthru('psql -f ~/test.sql', $output);

        //$result = $db->query( new Raw($sql) );

        //var_dump( $result->fetch(Result::FETCH_SINGLE|Result::TYPE_DETECT) );

//        print_r( $pg );

        //print_r($pg);
//        d($this);
//        d()->clear();
//        d("spanner");
    }
}