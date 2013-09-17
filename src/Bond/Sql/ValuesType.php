<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

use Bond\Sql;
use Bond\Sql\QuoteInterface;

class ValuesType extends Sql
{

    /**
     * @inheritdoc
     * @param array $value
     * @return ValuesType
     */
    public function __construct( array $value )
    {
        $this->value = $value;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoteInterface )
    {
        return sprintf(
            "VALUES(%s)",
            implode(
                '),(',
                array_map(
                    function(array $row) use ( $quoteInterface ) {
                        return implode(
                            ',',
                            array_map(
                                array( $quoteInterface, 'quote' ),
                                $row
                            )
                        );
                    },
                    $this->value
                )
            )
        );
    }

}