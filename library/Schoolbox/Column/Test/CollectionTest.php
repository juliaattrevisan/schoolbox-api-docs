<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Error;
use ICollection;
use Schoolbox\Column\Collection;
use Schoolbox\Test\TestMapper;
use Schoolbox\Test\TestModel;
use TypeError;

/**
 * Test for the collection column type.
 */
class CollectionTest extends TestCase
{
    /**
     * @var Collection
     */
    protected $object;

    /**
     * @var TestMapper|null
     */
    private static ?TestMapper $testMapper = null;

    /**
     * Get the test mapper
     */
    public static function getTestMapper(): TestMapper
    {
        self::$testMapper ??= new TestMapper();
        return self::$testMapper;
    }

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Collection();
    }

    /**
     * @param mixed            $input
     * @param ICollection|null $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestHydrate')]
    public function testHydrate($input = null, $expected_output = null)
    {
        $actual = $this->object->hydrate($input, self::$testMapper);

        self::assertInstanceOf(ICollection::class, $expected_output);
        self::assertInstanceOf(ICollection::class, $actual);
        self::assertSameSize($expected_output, $actual);

        $identifier = self::getTestMapper()->getIdentifierProperty();
        foreach ($expected_output as $i => $expectedItem) {
            $actualItem = $actual[$i];
            self::assertInstanceOf(TestModel::class, $expectedItem);
            self::assertInstanceOf(TestModel::class, $actualItem);
            self::assertSame(
                self::getTestMapper()->getModelProperty($actualItem, $identifier),
                self::getTestMapper()->getModelProperty($expectedItem, $identifier),
            );
        }
    }

    /**
     * @param mixed           $input
     * @param TestMapper|null $mapper
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestHydrateErrorCases')]
    public function testHydrateErrorCases($input, TestMapper $mapper = null): void
    {
        $this->expectException(TypeError::class);
        $this->object->hydrate($input, $mapper);
    }

    /**
     * @param mixed      $input
     * @param array|null $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDehydrate')]
    public function testDehydrate($input = null, $expected_output = null)
    {
        $actual = $this->object->dehydrate($input, self::$testMapper);
        self::assertSame($expected_output, $actual);
    }

    /**
     * @param mixed           $input
     * @param TestMapper|null $mapper
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDehydrateTypeErrorCases')]
    public function testDehydrateTypeErrorCases($input, TestMapper $mapper = null): void
    {
        $this->expectException(TypeError::class);
        $this->object->dehydrate($input, $mapper);
    }

    /**
     * @param mixed           $input
     * @param TestMapper|null $mapper
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDehydrateGetterNotDefinedErrorCases')]
    public function testDehydrateGetterNotDefinedErrorCases($input, TestMapper $mapper = null): void
    {
        $this->expectException(Error::class);
        $this->object->dehydrate($input, $mapper);
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        // I don't think that a collection can ever be dehydrated as `null`:
        // it will always be an array
        return [
            'null'          => [
                null,
                self::getTestMapper()->createCollection(),
            ],
            'none'          => [
                [],
                self::getTestMapper()->createCollection(),
            ],
            'one'           => [
                [1],
                self::getTestMapper()->createCollection([
                    self::getTestMapper()->createModel()->setId(1),
                ]),
            ],
            'two'           => [
                [1, 3],
                self::getTestMapper()->createCollection([
                    self::getTestMapper()->createModel()->setId(1),
                    self::getTestMapper()->createModel()->setId(3),
                ]),
            ],
            'array of null' => [
                [null],
                self::getTestMapper()->createCollection([
                    self::getTestMapper()->createModel(),
                ]),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrateErrorCases()
    {
        // Error cases:
        // mapper isn't set
        // input is not an array
        return [
            'not array' => ['', self::$testMapper],
            'no mapper' => [[], null],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return [
            'null'                  => [null, null],
            'empty array'           => [[], []],
            'empty collection'      => [self::getTestMapper()->createCollection(), []],
            'collection of null'    => [new \Schoolbox\Collection([null]), [null]],
            'collection of IDs'     => [
                new \Schoolbox\Collection([1, 2]),
                [1, 2],
            ],
            'collection of objects' => [
                self::getTestMapper()->createCollection([
                    self::getTestMapper()->createModel()->setId(1),
                    self::getTestMapper()->createModel()->setId(3),
                ]),
                [1, 3],
            ],
            'mixed collection'      => [
                new \Schoolbox\Collection([
                    self::getTestMapper()->createModel()->setId(1),
                    2,
                    self::getTestMapper()->createModel()->setId(3),
                ]),
                [1, 2, 3],
            ],
            'array of null'         => [[null], [null]],
            'array of IDs'          => [
                [1, 2],
                [1, 2],
            ],
            'array of objects'      => [
                [
                    self::getTestMapper()->createModel()->setId(1),
                    self::getTestMapper()->createModel()->setId(3),
                ],
                [1, 3],
            ],
            'mixed array'           => [
                [
                    self::getTestMapper()->createModel()->setId(1),
                    2,
                    self::getTestMapper()->createModel()->setId(3),
                ],
                [1, 2, 3],
            ],
        ];
    }

    /**
     * Data provider for `testDehydrateTypeErrorCases`
     */
    public static function getTestDehydrateTypeErrorCases(): iterable
    {
        return [
            'no mapper' => [self::getTestMapper()->createCollection(), null],
            'string'    => ['string', self::$testMapper],
        ];
    }

    /**
     * Data provider for `testDehydrateGetterNotDefinedErrorCases`
     */
    public static function getTestDehydrateGetterNotDefinedErrorCases(): iterable
    {
        $dummy = new class {
            public function getName()
            {
                return 'name';
            }
        };
        return [
            'getter not defined' => [[$dummy], self::$testMapper],
        ];
    }
}
