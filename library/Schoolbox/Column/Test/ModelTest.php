<?php

/**
 * Test for hydration/dehydration of models.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Model;
use Schoolbox\Test\TestMapper;
use Schoolbox\Test\TestModel;

class ModelTest extends TestCase
{
    /**
     * @var Model
     */
    protected $object;

    /**
     * @var TestModel
     */
    protected $test_model;

    /**
     * @var TestMapper
     */
    protected $test_mapper;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object     = new Model();
        $this->test_model = new TestModel();
        $this->test_model
            ->setId(1)
            ->setFilename('d.txt')
            ->setIsViewed(false)
            ->setFile('contents');
        $this->test_mapper = $this->getMockBuilder(TestMapper::class)
                                  ->onlyMethods(['findById', 'findOneByColumn'])
                                  ->getMock();
        $this->test_mapper
            ->expects($this->any())
            ->method('findById')
            ->with($this->logicalNot($this->isNull()))
            ->will($this->returnValue($this->test_model));
        $this->test_mapper
            ->expects($this->any())
            ->method('findById')
            ->with($this->isNull())
            ->will($this->returnValue(new TestModel()));
        $this->test_mapper
            ->expects($this->any())
            ->method('findOneByColumn')
            ->with('filename', 'd.txt')
            ->will($this->returnValue($this->test_model));
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return [];
    }

    /**
     * Function parameters not used, only kept to maintain compatibility with parent
     *
     * @param null $input
     * @param null $expected_output
     */
    public function testHydrate($input = null, $expected_output = null)
    {
        $hydrate = $this->object->hydrate(1, $this->test_mapper);
        $this->assertSame(1, $hydrate->getId());
    }

    /**
     * @inheritdoc
     */
    public function testHydrateNull()
    {
        $hydrate = $this->object->hydrate(null, $this->test_mapper);
        $this->assertNull($hydrate);
    }

    /**
     * Function parameters not used, only kept to maintain compatibility with parent
     *
     * @param null $input
     * @param null $expected_output
     */
    public function testDehydrate($input = null, $expected_output = null)
    {
        $this->assertSame(1, $this->object->dehydrate($this->test_model, $this->test_mapper));
    }

    /**
     * @inheritdoc
     */
    public function testDehydrateNull()
    {
        $this->assertSame(null, $this->object->dehydrate(null, $this->test_mapper));
    }

    /**
     * Test when dehydrating with an alternate field name.
     */
    public function testHydrateWithFieldName()
    {
        $this->object->setIdentifierProperty('filename');
        $hydrate = $this->object->hydrate('d.txt', $this->test_mapper);
        $this->assertSame('d.txt', $hydrate->getFilename());
    }

    /**
     * Test when dehydrating with an alternate field name.
     */
    public function testDehydrateWithFieldName()
    {
        $this->object->setIdentifierProperty('filename');
        $this->assertSame('d.txt', $this->object->dehydrate($this->test_model, $this->test_mapper));
    }
}
