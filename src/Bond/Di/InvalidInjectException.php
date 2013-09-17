<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Created by PhpStorm.
 * User: joseph
 * Date: 28/08/2013
 * Time: 11:47
 */

namespace Bond\Di;

use Exception;
use Symfony\Component\DependencyInjection\Definition;

class InvalidInjectException extends \LogicException {
    public function __construct(
        Definition $definition
    ) {
        parent::__construct(
            "definition for class {$definition->getClass()} cannot have Inject on it"
        );
    }

}