<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di\Tests;

class Person
{

    private $name;
    private $age;
    private $planet;
    private $parent;

    public function __construct($name, $age, $planet, Person $parent = null)
    {
        $this->name = $name;
        $this->age = $age;
        $this->planet = $planet;
        $this->parent = $parent;
    }

    /**
     * @param mixed $age
     */
    public function setAge($age)
    {
        $this->age = $age;
    }

    /**
     * @return mixed
     */
    public function getAge()
    {
        return $this->age;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param \Bond\Di\Tests\Person $parent
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * @return \Bond\Di\Tests\Person
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $planet
     */
    public function setPlanet($planet)
    {
        $this->planet = $planet;
    }

    /**
     * @return mixed
     */
    public function getPlanet()
    {
        return $this->planet;
    }

}