<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Exception;

use Bond\Pg;
use Bond\Pg\Result;
use Bond\Sql\Query;
use SebastianBergmann\Diff;

class AssetChangedException extends \Exception
{

    public $location;
    public $diffText;

    public function __construct( \SPLFileInfo $asset, Pg $db )
    {

        $this->location = realpath( $asset->getPathname() );

        $dbSql = $this->dbSql = $db->query(
            new Query(
                "SELECT * FROM build.assets WHERE location = %location:%",
                array(
                    'location' => $this->location
                )
            )
        )->fetch(Result::FETCH_SINGLE);

        if( $dbSql ) {
            $diff = new Diff();
            $this->diffText = $diff->diff(
                $dbSql['sql'],
                file_get_contents( $this->location )
            );
        } else {
            $this->diffText = 'Asset not in database';
        }

        $this->message = sprintf(
            "Database asset `%s` at %s changed at %s.\n%s",
            substr( $asset->getBasename(), 0, -4 ),
            $this->location,
            date( 'Y-m-d H:i:s', $asset->getMTime() ),
            $this->diffText
        );

    }

}