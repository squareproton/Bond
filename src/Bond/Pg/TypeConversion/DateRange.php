<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\TypeConversion;

use Bond\Entity\Types\DateRange as DateRangeEntity;
use Bond\Entity\Types\DateTime as DateTimeEntity;
use Bond\Database\TypeConversion;

class DateRange extends TypeConversion
{

    protected $refl;
    protected $bounds;
    protected $lower;
    protected $upper;

    public function __construct( $type )
    {

        parent::__construct($type);

        $this->refl = new \ReflectionClass( '\\Bond\\Entity\\Types\\DateRange' );
        $this->bounds = $this->refl->getProperty('bounds');
        $this->bounds->setAccessible(true);
        $this->lower = $this->refl->getProperty('lower');
        $this->lower->setAccessible(true);
        $this->upper = $this->refl->getProperty('upper');
        $this->upper->setAccessible(true);

    }

    public function __invoke($input)
    {

        $obj = $this->refl->newInstanceWithoutConstructor();

        // bounds
        $bounds = 0;
        $bounds += substr( $input, 0, 1 ) === "[" ? DateRangeEntity::LOWER_CONTAIN : DateRangeEntity::LOWER_CONTAIN_NOT;
        $bounds += substr( $input, -1 ) === "]" ? DateRangeEntity::UPPER_CONTAIN : DateRangeEntity::UPPER_CONTAIN_NOT;
        $this->bounds->setValue( $obj, $bounds );

        // ranges
        $input = explode( ",", substr( $input, 1, -1 ) );
        $this->lower->setValue( $obj, new DateTimeEntity( trim( $input[0], '"') ) );
        $this->upper->setValue( $obj, new DateTimeEntity( trim( $input[1], '"') ) );

        return $obj;

    }

}