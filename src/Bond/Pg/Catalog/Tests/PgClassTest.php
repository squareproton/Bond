<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Tests;

use Bond\Container;

use Bond\Pg\Catalog;
use Bond\Pg\Catalog\PgClass;
use Bond\Pg\Catalog\PgType;
use Bond\Pg\Result;

use Bond\Pg\Tests\PgProvider;

use Bond\Sql\Query;
use Bond\Sql\Raw;

class PgClassTest extends PgProvider
{

    public $connectionFactory;

    public function testRelationNameToOid()
    {

        $db = $this->connectionFactory->get('RW');
        $catalog = new Catalog($db);

        $a1_oid = $db->query( new Query("SELECT 'a1'::regclass::oid") )->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
        $this->assertSame( $a1_oid, $catalog->pgClasses->findOneByName('a1')->oid );
        $this->assertSame( 'a1', $catalog->pgClasses->findOneByOid($a1_oid)->name );

    }

    public function testRelationFindByName()
    {

        $db = $this->connectionFactory->get('RW');
        $catalog = new Catalog($db);

        $a1_oid = $db->query( new Query("SELECT 'a1'::regclass::oid") )->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );

        $a1_byoid = $catalog->pgClasses->findByOid( $a1_oid );
        $a1_byname = $catalog->pgClasses->findByName( 'unit.a1' );

        $this->assertTrue( $a1_byoid->isSameAs( $a1_byname ) );

    }

    public function testRelationGetColumns()
    {

        $db = $this->connectionFactory->get('RW');
        $catalog = new Catalog($db);

        $a1 = $catalog->pgClasses->findByName('unit.a1')->pop();

        $columns = $a1->getAttributes();
        $this->assertTrue( $columns instanceof Container );

        $columnsArray = $db->query(
            new Raw( <<<SQL
                SELECT
                    *
                FROM
                    pg_attribute
                WHERE
                    attrelid = 'a1'::regclass::oid AND
                    attnum >= 1
                ORDER BY
                    attnum
SQL
            )
        )->fetch();

        $namesArray = array_column( $columnsArray, 'attname');
        $this->assertSame(
            implode(',', $namesArray),
            $columns->implode(',','name')
        );

    }

    public function testRelationGetColumnsWithChild()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $parent = $catalog->pgClasses->findByName( 'unit.a1' )->pop();
        $child = $catalog->pgClasses->findByName( 'unit.a1_child' )->pop();

        $ncParent = $parent->getAttributes();
        $ncChild = $child->getAttributes();
        $ncChildOnly = $child->getAttributes( false );

        $this->assertSame( $ncParent->count() + $ncChildOnly->count(), $ncChild->count() );

    }

    public function testRelationGetColumnByName()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findByName( 'unit.a1' )->pop();

        $foreign_key = $a1->getAttributeByName('foreign_key');
        $id = $a1->getAttributeByName('id');
        $notARealColumn = $a1->getAttributeByName('notarealcolumn');

        $this->assertTrue( $foreign_key instanceof \Bond\Pg\Catalog\PgAttribute );
        $this->assertTrue( $id instanceof \Bond\Pg\Catalog\PgAttribute );
        $this->assertNull( $notARealColumn );

    }

    public function testRelationGetPrimaryKeys()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findByName( 'unit.a1' )->pop();

        $id = $a1->getAttributeByName('id');

        $pks = $a1->getPrimaryKeys();
        $this->assertSame( count($pks), 1 );
        $this->assertSame( $pks->shift(), $id );

        $pks = $a1->getPrimaryKeys();
        $unique = $a1->getUniqueAttributes();

        $this->assertTrue( $pks->isSameAs( $unique ) );

        // dual column primary keys
        $a3 = $catalog->pgClasses->findByName('unit.a3')->pop();
        $this->assertTrue( $a3->getPrimaryKeys()->isSameAs( $a3->getAttributes() ) );

    }

    public function testRelationGetUniqueConstraints()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findByName( 'unit.a1' )->pop();

        $attrs = $a1->getUniqueAttributes();
        // Pete. I don't like this. Too hacky.
        $id = $attrs->firstElementGet();

        $this->assertSame( count($attrs), 1 );
        $this->assertSame( $id->name, 'id' );

    }

    public function testAttributeGetReferencesBySQL()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $fk = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('foreign_key');
        $pk = $catalog->pgClasses->findOneByName('a2')->getAttributeByName('id');

        $this->assertTrue( $fk->getReferences()->contains($pk) );
        $this->assertTrue( $pk->getIsReferencedBy()->contains($fk) );

    }

    public function testAttributeGetReferencesByNormalityTags()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $fk = $catalog->pgClasses->findOneByName('a1_link_a4')->getAttributeByName('a1_id');
        $pk = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('id');

        $this->assertTrue( $fk->getReferences()->contains($pk) );
        $this->assertTrue( $pk->getIsReferencedBy()->contains($fk) );

    }

    public function testComments()
    {

        $catalog = new Catalog($db = $this->connectionFactory->get('RW'));

        $a3 = $catalog->pgClasses->findOneByName('a3'); // Relation::r()->findByName('unit.a1')->getAttributeByName('string');

        $this->assertSame(
            $db->query(new Raw("SELECT description FROM pg_description WHERE objoid = 'unit.a3'::regclass::oid;"))->fetch(Result::FETCH_SINGLE),
            $a3->comment
        );

        $string = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('string');

        $this->assertSame(
            $db->query(
                new Raw(
                    "SELECT description FROM pg_description WHERE objoid = 'unit.a1'::regclass::oid AND objsubid = {$string->attnum}"
                )
            )->fetch(Result::FETCH_SINGLE),
            $string->comment
        );

    }

    public function testAttributeIsUnique()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findOneByName('a1');

        $string = $a1->getAttributeByName('string');
        $id = $a1->getAttributeByName('id');

        $this->assertTrue( $id->isUnique() );
        $this->assertFalse( $string->isUnique() );

    }

    public function testRelationGetReferencesBySQL()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findOneByName('a1');

        $references = $a1->getReferences();

        $pk = $catalog->pgClasses->findOneByName('a1_link_a4')->getAttributeByName('a1_id');

        $this->assertEquals(
            $references['A1linkA4.a1_id'],
            array(
                'A1linkA4', 'a1_id', 1
            )
        );

    }

    public function testRelationGetReferencesByNormalityCommentsInheritance()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $fk = $catalog->pgClasses->findOneByName('a1_link_a4')->getAttributeByName('a1_id');
        $pk = $catalog->pgClasses->findOneByName('a1_child')->getAttributeByName('id');

        $this->assertTrue( $fk->getReferences()->contains($pk) );
        $this->assertTrue( $pk->getIsReferencedBy()->contains($fk) );

    }

    public function testRelationGetReferencesChild()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $child = $catalog->pgClasses->findOneByName('a1_child');

        $references = $child->getReferences();

        $this->assertEquals(
            $references['A11.a1_id'],
            array(
                'A11', 'a1_id', 0
            )
        );

        $this->assertEquals(
            $references['A1linkA4.a1_id'],
            array(
                'A1linkA4', 'a1_id', 1
            )
        );

        $this->assertEquals(
            $references['Refa1child.a1_child_id'],
            array(
                'Refa1child', 'a1_child_id', 1
            )
        );

    }

    public function testRelationIsView()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $a1 = $catalog->pgClasses->findOneByName('a1');
        $view = $catalog->pgClasses->findOneByName('view');

        $this->assertFalse( $a1->isView() );
        $this->assertTrue( $view->isView() );

    }

    public function testRelationGetLinks()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $links = $catalog->pgClasses->findOneByName('a1')->getLinks();

        $this->assertEquals(
            $links['A1linkA4']->toArray(),
            array(
                'A1',
                'A1linkA4',
                array( 'A4' ),
                array( 'id', 'a1_id' ),
                array( 'a4_id', 'id' ),
                'a4_idranking'
            )
        );

    }

    public function testRelationGetLinksChild()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $links = $catalog->pgClasses->findOneByName('a1_child')->getLinks();

        $this->assertEquals(
            $links['A1linkA4']->toArray(),
            array(
                'A1child',
                'A1linkA4',
                array( 'A4' ),
                array( 'id', 'a1_id' ),
                array( 'a4_id', 'id' ),
                'a4_idranking'
            )
        );

    }

    public function testRelationGetChildLinksFromOtherSide()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $links = $catalog->pgClasses->findOneByName('a4')->getLinks();

        $this->assertEquals(
            $links['A1linkA4']->toArray(),
            array(
                'A4',
                'A1linkA4',
                array( 'A1', 'A1child'),
                array( 'id', 'a4_id' ),
                array( 'a1_id', 'id' ),
                'a1_idranking'
            )
        );

    }

    public function testRelationIsLogTable()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findOneByName('a1');
        $log = $catalog->pgClasses->findOneByName('log');

        $this->assertFalse( $a1->isLogTable() );
        $this->assertTrue( $a1->isLogTable( true ) );
        $this->assertTrue( $log->isLogTable() );

    }

    public function testRelationIsMaterialisedView()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $a1 = $catalog->pgClasses->findOneByName('a1');
        $mView = $catalog->pgClasses->findOneByName('mView');

        $this->assertFalse( $a1->isMaterialisedView() );
        $this->assertTrue( $a1->isMaterialisedView(true) );
        $this->assertTrue( $mView->isMaterialisedView() );

    }

    public function testRelationGetParentChildren()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $a1 = $catalog->pgClasses->findOneByName('a1');
        $a1_child = $catalog->pgClasses->findOneByName('a1_child');

        $this->assertNull( $a1->getParent() );
        $this->assertSame( $a1_child->getParent(), $a1 );

        $children = $a1->getChildren();
        $this->assertTrue( $children instanceof Container );
        $this->assertTrue( $children->isSameAs( new Container($a1_child) ) );

    }

    public function testLength()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $cc = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('cc');

        $this->assertSame( $cc->length, 2 );

    }

    public function testAttributeType()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findOneByName('a1');
        $cc = $a1->getAttributeByName('cc');
        $type = $cc->getType();

        $this->assertTrue( $type instanceof PgType );
        $this->assertSame( $cc->getType(), $cc->getType() );

    }

    public function testAttributePrimaryKey()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $a1 = $catalog->pgClasses->findOneByName('a1');
        $a1_link_a4 = $catalog->pgClasses->findOneByName('a1_link_a4');

        $string = $a1->getAttributeByName('string');
        $id = $a1->getAttributeByName('id');

        $this->assertTrue( $id->isPrimaryKey() );
        $this->assertFalse( $string->isPrimaryKey() );

        // multi-primary key link table
        $this->assertTrue( $a1_link_a4->getAttributeByName('a1_id')->isPrimaryKey() );
        $this->assertTrue( $a1_link_a4->getAttributeByName('a4_id')->isPrimaryKey() );
        $this->assertFalse( $a1_link_a4->getAttributeByName('a1_id')->isUnique() );
        $this->assertFalse( $a1_link_a4->getAttributeByName('a4_id')->isUnique() );

    }

    public function testAttributeIsInheritied()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $a1 = $catalog->pgClasses->findOneByName('a1');
        $id = $a1->getAttributeByName('id');
        $string = $a1->getAttributeByName('string');

        $this->assertFalse( $string->isInherited() );
        $this->assertFalse( $id->isInherited( $originalColumn ) );
        $this->assertNull( $originalColumn );

        $a1_child = $catalog->pgClasses->findOneByName('a1_child');
        $a1_child_id = $a1_child->getAttributeByName('id');
        $col1 = $a1_child->getAttributeByName('col1');

        $this->assertFalse( $col1->isInherited() );
        $this->assertTrue( $a1_child_id->isInherited( $originalColumn ) );
        $this->assertSame( $originalColumn, $id );

    }

    public function testAttributeGetChildren()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $a1_id = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('id');
        $a1_child_id = $catalog->pgClasses->findOneByName('a1_child')->getAttributeByName('id');

        $this->assertTrue(
            $a1_id->getChildren()->isSameAs( new Container( $a1_child_id ) )
        );

        $this->assertTrue(
            $a1_child_id->getChildren()->isEmpty()
        );

    }

     public function testTypeIsBool()
     {
        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $bool = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('b')->getType();
        $cc = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('cc')->getType();

        $this->assertTrue( $bool->isBool() );
        $this->assertFalse( $cc->isBool() );
    }

    public function testTypeIsNumeric()
    {
        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $int = $catalog->pgClasses->findOneByName('a1_link_a4')->getAttributeByName('a1_idranking')->getType();
        $cc = $catalog->pgClasses->findOneByName('a1')->getAttributeByName('cc')->getType();

        $this->assertTrue( $int->isNumeric() );
        $this->assertFalse( $cc->isNumeric() );
    }

    public function testTypeFindByName()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $enumtype = $catalog->pgTypes->findOneByName( 'enumtype' );
        $attrType = $catalog->pgClasses->findOneByName('a4')->getAttributeByName('type')->getType();

        // init enum by name
        $this->assertSame( $enumtype, $attrType );

    }

    public function testEnumType()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));

        $enumtype = $catalog->pgTypes->findOneByName( 'enumtype' );
        $citext = $catalog->pgTypes->findOneByName( 'text' );

        $this->assertTrue( $enumtype->isEnum() );
        $this->assertFalse( $citext->isEnum() );

        $this->assertTrue( is_array( $enumtype->enumOptions ) );
        $this->assertSame( $enumtype->enumOptions, array( 'one','two') );

    }

#    public function testSequence()
#    {
#
#        $repo = Sequence::r();
#
#        $sequences = $repo->findByTypes();
#        $a4_id_seq = $sequences->findByName( 'a4_id_seq' )->pop();
#        $this->assertSame( $a4_id_seq, $repo->findByName('a4_id_seq') );
#
#        $this->assertNull( $repo->findByName('notavalidsequence') );
#
#        $this->assertNull( $a4_id_seq->getCurval() );
#
#    }

    public function testAttributeGetSequence()
    {

        return;
        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a1 = $catalog->pgClasses->findOneByName('a1');
        $id = $a1->getAttributeByName('id');
        $cc = $a1->getAttributeByName('cc');

        $this->assertTrue( $id->getSequence() instanceof PgClass );
        $this->assertNull( $cc->getSequence() );

    }

    public function testAttributeDefault()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $a2 = $catalog->pgClasses->findOneByName( 'a2' );

        // default values test
        $this->assertTrue( strlen( $a2->getAttributeByName('id')->default ) > 0 );
        $this->assertNull( $a2->getAttributeByName('name')->default );

    }

    public function testRecursiveTablesPrimaryKeyBug()
    {

        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $r1 = $catalog->pgClasses->findOneByName( 'r1' );

        $pks = $r1->getPrimaryKeys();

        $columns = $r1->getAttributes();

        $this->assertTrue( $columns->shift()->isPrimaryKey() );
        $this->assertFalse( $columns->shift()->isPrimaryKey() );
        $this->assertFalse( $columns->shift()->isPrimaryKey() );

    }

    public function testEnums()
    {
        $catalog = new Catalog($this->connectionFactory->get('RW'));
        $this->assertTrue( $catalog->enum instanceof \Bond\Database\Enum );
    }

    /**/

}