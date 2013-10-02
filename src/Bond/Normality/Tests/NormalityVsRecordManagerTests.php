<?php

namespace Bond\Normality\Tests;

use Bond\Pg\Result;
use Bond\Sql\Query;

class DeleteTest extends NormalityProvider
{

    public function testInitByDatasBug()
    {

        $setupSql= <<<SQL
            INSERT INTO a2 ( id, name ) VALUES ( 102, 'name 202' );
            INSERT INTO a1 ( id, int4, string, create_timestamp, foreign_key ) VALUES ( 101, 1234, 'hi', now(), 102 );
SQL
;
        $this->db->query( new Query($setupSql) );

        $a2s = $this->A2->initByDatas(
            $this->db->query(new Query('SELECT * FROM a2'))->fetch(Result::TYPE_DETECT)
        );

        //d( $this->A2->findAll() );
        //$a2 = $this->A2->init
        $newA1 = $this->A1->make();

    }

}