<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Bond\Pg;

use Bond\MagicGetter;

/**
 * @author Pete
 */
class Oid implements SqlInterface
{

    use MagicGetter;

    /**
     * Valid json string
     */
    private $oid;

    /**
     * Database this object belongs to
     */
    private $pg;

    /**
     * Represents a database oid. This number specific to a database, hence the reference to Pg
     * @param int Oid
     * @param Bond\Pg
     */
    public function __construct( $oid, Pg $pg )
    {

        if( !\Bond\is_intish( $oid ) ) {
            throw new BadTypeException( $input, 'int' );
        }

        $this->oid = (int) $oid;
        $this->pg = $pg;

    }

    /**
     * __toString. Returns oid.
     * Objects instantiated with initFromObject are lazily json_encoded
     * @return string
     */
    public function __toString()
    {
        return (string) $this->oid;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {
        return $quoting->quote( $this->oid );
    }

}