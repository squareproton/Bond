<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Builder;

/**
 * Entity generation
 *
 * @author pete
 */
class EntityChild extends Entity
{

    /**
     * Standard constructor
     */
    public function __construct()
    {
        call_user_func_array(
            'parent::__construct',
            func_get_args()
        );
    }

    /**
     * Get class extends
     */
    protected function getClassExtends()
    {
        $parentPlaceholderClass = sprintf(
            "\\%s\\%s",
            $this->options['entityPlaceholder'],
            $this->pgClass->getParent()->getEntityName()
        );
        $this->class->addExtends($parentPlaceholderClass);
    }

}