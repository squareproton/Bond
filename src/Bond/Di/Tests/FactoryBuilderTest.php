<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di\Tests;

use Bond\Di\DiTestCase;
use Bond\Di\Factory;
use Bond\Di\Inject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Bond\Di\Configurator;

use Bond\Di\Tests\Di\FactoryBuilderTestConfiguration;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

use Bond\Di\FactoryBuilderExtension;

/**
 * Class FactoryBuilderTest
 * @package Bond\Di\Tests
 * @service factoryBuilderTest
 * @resource __CLASS__
 */
class FactoryBuilderTest extends DiTestCase
{
    public $container;
    public $vanillaPersonFactory;
    public $personInjectAllFactory;
    public $personFirstThreeArgumentsInjectedFactory;
    public $childFactory;
    public $personFactory;

    public function createPerson()
    {
        return new Person("testname100", 100, "earth100", null);
    }

    public function __invoke(Configurator $configurator, $container)
    {
        $configurator->add(
            "vanillaPerson",
            Person::class,
            ["myname", 100, "Earth", null],
            "prototype",
            true
        );

        $configurator->add(
            "personInjectAll",
            Person::class,
            [new Inject(), new Inject(), new Inject(), new Inject()],
            "prototype",
            true
        );

        $configurator->add(
            "personFirstThreeArgumentsInjected",
            Person::class,
            [
                new Inject(),
                new Inject(),
                new Inject(),
                new Reference("vanillaPerson")
            ],
            "prototype",
            true
        );

        $configurator->add(
            "child",
            Person::class,
            ["myname", 25, "Earth", new Reference("mother")],
            "prototype",
            true
        );
        $configurator->add(
            "mother",
            Person::class,
            ["myname", 50, "Earth", new Reference("vanillaPerson")]
        );

        $configurator->add(
            "factoryBuilderTest",
            self::class
        );

        $configurator->add(
            "personBuilder",
            Person::class
        )
            ->setFactoryService("factoryBuilderTest")
            ->setFactoryMethod("createPerson");

        $configurator->addFactory(
            "personFactory",
            "personBuilder"
        );

        $configurator->add(
            "factoryBuilderTest",
            self::class
        )->setProperties(
                [
                    "container" => new Reference("service_container"),
                    "vanillaPersonFactory" => new Reference("vanillaPersonFactory"),
                    "personInjectAllFactory" => new Reference("personInjectAllFactory"),
                    "personFirstThreeArgumentsInjectedFactory" => new Reference("personFirstThreeArgumentsInjectedFactory"),
                    "childFactory" => new Reference("childFactory"),
                    "personFactory" => new Reference("personFactory")
                ]
            );

    }

    public function testPersonFactoryType()
    {
        $this->assertInstanceOf(Factory::class, $this->personInjectAllFactory);
        $this->assertInstanceOf(
            Factory::class,
            $this->personFirstThreeArgumentsInjectedFactory
        );
    }

    public function testPersonType()
    {
        $this->assertInstanceOf(
            Person::class,
            $this->vanillaPersonFactory->create()
        );
    }

    public function testParentIsAlsoAPerson()
    {
        $this->assertInstanceOf(
            Person::class,
            $this->personFirstThreeArgumentsInjectedFactory->create(
                "h",
                1,
                "e"
            )->getParent()
        );
    }

    public function provideAllServicesDefined()
    {
        return [
            ["vanillaPerson"],
            ["vanillaPersonFactory"],
            ["personInjectAll"],
            ["personInjectAllFactory"],
            ["personFirstThreeArgumentsInjected"],
            ["personFirstThreeArgumentsInjectedFactory"],
            ["factoryBuilderTest"],
            ["child"],
            ["mother"],
            ["personBuilder"],
            ["personFactory"]
        ];
    }

    /** @dataProvider provideAllServicesDefined */
    public function testContainerHasAllServicesDefinedInConfiguration(
        $definitionId
    ) {
        $this->assertTrue($this->container->hasDefinition($definitionId));
    }

    /** @dataProvider provideAllServicesDefined */
    public function testMergingContainsHaveAllServices($definitionId)
    {
        $container2 = new ContainerBuilder();
        $container2->merge($this->container);
        $this->assertTrue($container2->hasDefinition($definitionId));
    }

    /** @dataProvider provideAllServicesDefined */
    public function testMergingContainsHaveAllServicesAfterCompilation($definitionId)
    {
        $container2 = new ContainerBuilder();
        $container2->merge($this->container);
        $container2->compile();
        $this->assertTrue($container2->hasDefinition($definitionId));
    }

    public function provideAllFactoryServicesDefined()
    {
        return [
            ["vanillaPersonFactory"],
            ["personInjectAllFactory"],
            ["personFirstThreeArgumentsInjectedFactory"]
        ];
    }

    /** @dataProvider provideAllFactoryServicesDefined */
    public function testAllFactoriesCanBeInstantiated($definitionId)
    {
        $this->assertInstanceOf(
            Factory::class,
            $this->container->get($definitionId)
        );
    }

    public function provideArgumentsForCreateMethodThatWillThrowExceptionOnFactoryPersonInjectAllFactory(
    )
    {
        return [
            [],
            ["testname"],
            ["testname", 21],
            ["testname", 21, "testplanet"],
            ["testname", 21, "testplanet", "person", "person1"],
            [new Inject(), new Inject(), new Inject(), new Inject()]
        ];
    }

    /**
     * @dataProvider provideArgumentsForCreateMethodThatWillThrowExceptionOnFactoryPersonInjectAllFactory
     * @expectedException Exception
     */
    public function testCreateMethodOnFactoryThrowsExceptionForFactoryPersonInjectAllFactory(
    )
    {
        call_user_func_array(
            [$this->personInjectAllFactory, "create"],
            func_get_args()
        );
    }

    public function provideArgumentsForCreateMethodThatWillThrowExceptionOnFactoryPersonFirstThreeArgumentsInjectedFactory()
    {
        return [
            [],
            ["testname"],
            ["testname", 21],
            ["testname", 100, "testplanet", null],
            [
                "testname",
                100,
                "testplanet",
                new Person("testname", 100, "testplanet", null)
            ],
            ["testname", 100, "testplanet", null, "other"]
        ];
    }

    /**
     * @dataProvider provideArgumentsForCreateMethodThatWillThrowExceptionOnFactoryPersonFirstThreeArgumentsInjectedFactory
     * @expectedException Exception
     */
    public function testCreateMethodOnFactoryThrowsExceptionForFactoryPersonFirstThreeArgumentsInjectedFactory()

    {
        call_user_func_array(
            [$this->personFirstThreeArgumentsInjectedFactory, "create"],
            func_get_args()
        );
    }

    public function provideValidArgumentsForCreateMethodOnPersonFirstThreeArgumentsInjectedFactory()
    {
        return [
            ["testname", 21, "Earth"],
            ["", -5, ""],
            ["anyname", 1000.0, "China"]
        ];
    }

    /** @dataProvider provideValidArgumentsForCreateMethodOnPersonFirstThreeArgumentsInjectedFactory */
    public function testPersonCreatedFromFactoryWithCorrectArgumentsDoesNotThrowException($name, $age, $planet)
    {
        $this->personFirstThreeArgumentsInjectedFactory->create($name, $age, $planet);
    }

    /** @dataProvider provideValidArgumentsForCreateMethodOnPersonFirstThreeArgumentsInjectedFactory */
    public function testPersonCreatedFromFactoryHasCorrectValues($name, $age, $planet)
    {
        $person = $this->personFirstThreeArgumentsInjectedFactory->create($name, $age, $planet);
        $this->assertEquals($name, $person->getName());
        $this->assertEquals($age, $person->getAge());
        $this->assertEquals($planet, $person->getPlanet());
    }

    public function testNestedReferencesAreCorrectlyResolved()
    {
        $child = $this->childFactory->create();
        $mother = $child->getParent();
        $grandmother = $mother->getParent();

        $this->assertEquals(25, $child->getAge());
        $this->assertEquals(50, $mother->getAge());
        $this->assertEquals(100, $grandmother->getAge());
    }

    public function testAPersonCanBeBuildFromAFactory()
    {
        $this->assertNotNull($this->container->get("personBuilder"));
        $this->assertInstanceOf(Person::class, $this->container->get("personBuilder"));
    }

    public function testPersonFactoryDoesNotThrowExceptionWhenCreateCalled()
    {
        $this->personFactory->create();
    }

    public function testPersonFactoryCreateReturnsPerson()
    {
        $this->assertInstanceOf(
            Person::class,
            $this->personFactory->create()
        );
    }

    public function testPersonCreatedFromPersonFactoryHasCorrectProperties()
    {
        $person = $this->personFactory->create();
        $this->assertEquals("testname100", $person->getName());
        $this->assertEquals(100, $person->getAge());
        $this->assertEquals("earth100", $person->getPlanet());
        $this->assertNull($person->getParent());
    }

}