<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

    /**
     * Filter entities by their changed status.
     * @param bool $changed. Remove entities which are changed
     * @param bool $uchanged. Remove unchanged entities
     * @return $this
     */
    public function filterByIsChanged( $changed = false, $unchanged = false )
    {

        $changed = \Bond\boolval( $changed );
        $unchanged = \Bond\boolval( $unchanged );

        if( $changed and $unchanged ) {
            $this->collection = array();
            return $this;
        }

        if( !$changed and !$unchanged ) {
            return $this;
        }

        $this->collection = array_filter(
            $this->collection,
            function ($entity) use ( $changed ) {
                return $entity->isChanged() !== $changed;
            }
        );

        return $this;

    }