<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Tests;

use Bond\Normality\MatchRelation\Closure;
use Bond\Pg\Catalog;

use Bond\Normality\Options;
use Bond\Normality;

class NormalityTest extends \Bond\Tests\EntityManagerProvider
{

    public function testRelationNameToOid()
    {

        $catalog = new Catalog( $this->entityManager->db );
        $options = new Options( $catalog, __DIR__.'/../../../' );

        $options->setPath(
            array(
                'entity' => '/Bond/Normality/UnitTest/Entity/Normality',
                'entityPlaceholder' => '/Bond/Normality/UnitTest/Entity',
                'repository' => '/Bond/Normality/UnitTest/Repository/Normality',
                'repositoryPlaceholder' => '/Bond/Normality/UnitTest/Repository',
                'pgRecordConverter' => '/Bond/Normality/UnitTest/PgRecordConverter',
                'register' => '/Bond/Normality/UnitTest/Entity/Register',
                'log' => '/Bond/Normality/UnitTest/Logs',
                'backup' => '/Bond/Normality/UnitTest/Backups',
                'entityFileStore' => '/Bond/Normality/UnitTest/EntityFileStore',
            )
        );

        $options->prepareOptions = Options::BACKUP;

        $options->matches[] = new Closure(
            function($relation){
                return $relation->name === 'typ';
                return in_array( $relation->schema, [ 'unit', 'logs', 'common' ] );
            }
        );

        $normality = new Normality($options);

        $built = $normality->build();

    }

}

#
#namespace Bond\Normality\Tests;
#
#use Bond\Normality;
#
#use Bond\Pg\Connection;
#use Bond\Sql\Query;
#
#use Bond\Normality\Tests\NormalityProvider;
#
#class NormalityTest extends NormalityProvider
#{
#
#    public function testextractTags()
#    {
#
#        // vanilla tags
#        $this->assertSame( \Bond\extract_tags('', 'normality'), array() );
#        $this->assertSame( \Bond\extract_tags('@normality.match: match', 'normality'), array( 'match' => 'match' ) );
#        $this->assertSame( \Bond\extract_tags("@normality.match: match\n@normality.linktype: spanner", 'normality'), array('match' => 'match', 'linktype' => 'spanner' ) );
#        $this->assertSame( \Bond\extract_tags("\n\n@normality.match: match", 'normality'), array('match' => 'match' ) );
#
#        $comment = <<<COMMENT
#@normality.match: unit\n
#@normality.persist[]: RELOAD\n
#@normality.persist[]: FETCH_DEFAULTS\n
#@normality.singleton: true\n
#COMMENT
#;
#        $tags = array(
#            'match' => 'unit',
#            'persist' => array(
#                'RELOAD',
#                'FETCH_DEFAULTS',
#            ),
#            'singleton' => true,
#        );
#
#        $this->assertSame( \Bond\extract_tags( $comment, 'normality' ), $tags );
#
#        // mixed tags
#        $this->assertSame( \Bond\extract_tags( "spanner\n@normality.form-choicetext: true\n", 'normality' ), array( 'form-choicetext' => true ) );
#        $this->assertSame( \Bond\extract_tags( "@normality.form-choicetext", 'normality' ), array( 'form-choicetext' => true ) );
#
#    }
#
#}