<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use ICollection;
use Schoolbox\Column\Collection;
use Schoolbox\Test\TestMapper;
use Schoolbox\Test\TestModel;

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
     * @var TestMapper
     */
    private TestMapper $testMapper;

    /**
     * @inheritdoc
     */
    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->testMapper = new TestMapper();
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
     * @dataProvider getTestHydrate
     */
    public function testHydrate($input = null, $expected_output = null)
    {
        $actual = $this->object->hydrate($input, $this->testMapper);

        self::assertInstanceOf(ICollection::class, $expected_output);
        self::assertInstanceOf(ICollection::class, $actual);
        self::assertSameSize($expected_output, $actual);

        $identifier = $this->testMapper->getIdentifierProperty();
        foreach ($expected_output as $i => $expectedItem) {
            $actualItem = $actual[$i];
            self::assertInstanceOf(TestModel::class, $expectedItem);
            self::assertInstanceOf(TestModel::class, $actualItem);
            self::assertSame(
                $this->testMapper->getModelProperty($actualItem, $identifier),
                $this->testMapper->getModelProperty($expectedItem, $identifier),
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function getTestHydrate()
    {
        // I don't think that a collection can ever be dehydrated as `null`:
        // it will always be an array
        return [
            'null' => [
                null,
                $this->testMapper->createCollection(),
            ],
            'none' => [
                [],
                $this->testMapper->createCollection(),
            ],
            'one'  => [
                [1],
                $this->testMapper->createCollection([
                    (new TestModel())->setId(1),
                ]),
            ],
            'two'  => [
                [1, 3],
                $this->testMapper->createCollection([
                    (new TestModel())->setId(1),
                    (new TestModel())->setId(3),
                ]),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getTestDehydrate()
    {
        return [
            [null, null],
        ];
    }
}
