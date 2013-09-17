<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\TypeConversion;

use Bond\Normality\Entity\StockState as StockStateEntity;
use Bond\Pg\TypeConversion;

class StockState extends TypeConversion
{

    public function __invoke($input)
    {

        if( null === $input ) {
            return null;
        }

        $data = explode( ',',  trim( $data, '()' ) );
        $locationId = \Bond\nullify( $data[0] );
        $contactId = \Bond\nullify( $data[1] );
        $orderDetailId = \Bond\nullify( $data[2] );

        $data = array(
            'locationId' => $locationId ? intval( $locationId ) : null,
            'contactId' => $contactId ? intval( $contactId ) : null,
            'orderDetailId' => $orderDetailId ? intval( $orderDetailId ) : null,
            'propertiesId' => (int) $data[3],
        );

        return new StockStateEntity( $data );

    }

}