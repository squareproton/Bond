<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql\Tests;

use Bond\Pg\Connection;
use Bond\Pg\Result;

use Bond\Sql\Query;
use Bond\Sql\ValuesType;

use Bond\Pg\Tests\PgProvider;

class SqlTest extends PgProvider
{

    public function testValuesType()
    {

        $db = $this->connectionFactory->get('RW');

        $array = array(
            array(1, 2, 3),
        );

        $values = new ValuesType($array);

        $query = new Query(
            'SELECT * FROM (%array:%) AS "Array"',
            array(
                'array' => $values
            )
        );

        $result = $db->query($query);

        $this->assertSame(
            $array,
            $result->fetch( Result::STYLE_NUM | Result::TYPE_DETECT )
        );

        $array = array(
            1,
            2,
            3
        );

        $values = new ValuesType($array);
        $query->array = $values;

        try {

            $db->query($query)->fetch( Result::STYLE_NUM | Result::TYPE_DETECT );
            $this->assertTrue(false);

        } catch (\Exception $e) {

            $this->assertTrue(true);
        }

    }

    public function testSerialization()
    {
        $queryIn = new Query('fucking bugs suck', array('annoying severity', 'x4 corrosive') );
        $s = $queryIn->serialize();
        $queryOut = \Bond\unserialize_t( $s );
    }

}