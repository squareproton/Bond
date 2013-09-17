<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog;

use Bond\Sql\Query;
use Bond\Sql\Raw;
use Bond\Pg;
use Bond\Pg\Result;
use Bond\Pg\Catalog;

use Bond\Container as BaseContainer;
use Bond\Container\PropertyMapperObjectAccess;

class Container extends BaseContainer
{

    protected $setupSql;

    public $catalog;

    public function __construct( Catalog $catalog )
    {

        $this->catalog = $catalog;
        $this->setPropertyMapper(PropertyMapperObjectAccess::class);

        // query the database and get all the relations
        $query = new Raw( $this->setupSql );
        $result = $this->catalog->db->query($query)->fetch(Result::TYPE_DETECT);

        $refl = new \ReflectionClass($this->class);
        foreach( $result as $row ) {
            $obj = $refl->newInstanceArgs([$catalog, $row]);
            $this->collection[spl_object_hash($obj)] = $obj;
        }

       }

    public function newContainer()
    {
        $container = new BaseContainer();
        $container->classSet($this->class);
        $container->add(func_get_args());
        $container->propertyMapper = $this->propertyMapper;
        return $container;
    }

}