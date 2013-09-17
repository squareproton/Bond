<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql\Tests;

use Bond\Pg\Tests\PgProvider;
use Bond\Sql\Raw;

class RawTest extends PgProvider
{

    public function testInstantiation()
    {

        $db = $this->connectionFactory->get('RW');
        $query = new Raw( $input = "SomeVeryUnsafeSQLHere" );
        $this->assertSame( $query->parse($db), $input );

    }

}