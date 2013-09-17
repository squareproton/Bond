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

/**
 * @author Pete
 * @author Matt
 */
class Inet implements SqlInterface, \JsonSerializable
{

    /**
     * Internal representation of a ipaddress. Haven't yet decided to make this integer or human readable yet
     */
    protected $ipAddress;

    /**
     * @param mixed Human readable ip address
     */
    public function __construct( $ipAddress )
    {
        if( false === $filteredIpAddress = filter_var( $ipAddress, FILTER_VALIDATE_IP ) ) {
            throw new \InvalidArgumentException(
                "IP address passed to new Inet() `$ipAddress` isn't a valid IP address"
            );
        }
        $this->ipAddress = $filteredIpAddress;
    }

    /**
     * Get the IP address as a string
     * @return string
     */
    public function __toString()
    {
        return (string) $this->ipAddress;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quotingInterface )
    {
        // the ip address has been validated in the constructor. Escaping not necissary!
        return sprintf(
            "'%s'",
            $this->ipAddress
        );
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return (string) $this->ipAddress;
    }

}