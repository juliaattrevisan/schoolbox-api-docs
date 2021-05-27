<?php

/**
 * Base test case for column types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\IColumnType;
use Schoolbox\Test\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    /**
     * @var IColumnType
     */
    protected $object;

    /**
     * Creates the column type object that is being tested..
     *
     * @return void
     */
    abstract public function setupObject();

    /**
     * Sets up mock config object, in case it's needed.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->setupObject();
    }

    /**
     * Data provider for $this->testHydrate().
     *
     * @return array
     */
    abstract public function getTestHydrate();

    /**
     * Data provider for $this->testDehydrate().
     *
     * @return array
     */
    abstract public function getTestDehydrate();


    /**
     * @dataProvider getTestHydrate
     * @param mixed $input
     * @param mixed $expected_output
     */
    public function testHydrate($input = null, $expected_output = null)
    {
        $this->assertSame($expected_output, $this->object->hydrate($input));
    }

    /**
     * @dataProvider getTestDehydrate
     * @param mixed $input
     * @param mixed $expected_output
     */
    public function testDehydrate($input = null, $expected_output = null)
    {
        $this->assertSame($expected_output, $this->object->dehydrate($input));
    }
}
