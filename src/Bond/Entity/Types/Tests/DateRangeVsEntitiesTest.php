<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use Bond\Entity\Types\DateTime;
use Bond\Entity\Types\DateRange;

use Bond\Sql\Query;
use Bond\Pg\Result;

class DateRangeVsEntitiesTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function testConstruct()
    {

        $r = $this->R2->make();

        $r['range'] = new DateRange(
            $start = new DateTime(),
            $end = (new DateTime())->add('P4D')
        );

        $r['range'] = "[2010-01-01,2010-01-02)";

        $rm = $this->entityManager->recordManager;
        $rm->persist($r);
        $rm->flush();

        $result = $this->db->query( new Query( "SELECT * FROM r2" ) )->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );

        $this->assertTrue( $result['range'] instanceof DateRange );
        $this->assertSame( (string) $result['range'], (string) $r['range'] );

    }

}