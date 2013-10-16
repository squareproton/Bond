<?php

namespace Bond\Entity\Tests;

use Bond\Sql\Query;
use Bond\Sql\Raw;
use Bond\Pg\Result;

class DateRangeVsEntitiesTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function testSomething()
    {
        // populate the database and get stuff
        $this->populateTyp( $n = 2);
        $result = $this->db->query( new Query( "SELECT ROW(typ.*)::typ FROM typ") )->fetch(Result::TYPE_DETECT);

        $this->assertSame( $n, $this->Typ->findAll()->count() );
        $this->assertSame( $n, $this->Typ->findPersisted()->count() );
        $this->assertSame( 0, $this->Typ->findUnpersisted()->count() );

        // get some more shit from the database that is regarded as new - ie it doesn't have a primary key
        $query = new Raw( "SELECT ROW( null, 100-gi, ARRAY[gi]::text[], ARRAY[1,2]::INT[], gi, '--', gi % 2 = 0, now() )::typ FROM generate_series(1,$n) g(gi);" );
        $result = $this->db->query($query)->fetch(Result::TYPE_DETECT);

        $this->assertSame( 2*$n, $this->Typ->findAll()->count() );
        $this->assertSame( $n, $this->Typ->findPersisted()->count() );
        $this->assertSame( $n, $this->Typ->findUnpersisted()->count() );

    }

    public function populateTyp($n)
    {
        $this->db->query(
            new Raw( <<<SQL
                INSERT INTO typ (i, ta, ia, s, c, b, t)
                SELECT 100-gi, ARRAY[gi]::text[], ARRAY[1,2]::INT[], gi, '--', gi % 2 = 0, now() FROM generate_series(1,$n) g(gi);
SQL
            )
        );
    }

}