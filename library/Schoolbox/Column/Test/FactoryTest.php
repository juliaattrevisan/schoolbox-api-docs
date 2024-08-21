<?php

/**
 * Tests for integer column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Factory;
use Schoolbox\Test\TestCase as BaseTestCase;
use Schoolbox\Mapper as Mapper;

class FactoryTest extends BaseTestCase
{
    /**
     * @var Factory
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setup(): void
    {
        $this->object = new Factory();
    }

    /**
     * @inheritdoc
     */
    public static function getTestBuild()
    {
        return array(
            array('\\Schoolbox\\Column\\Base',       Mapper::TYPE_STRING),
            array('\\Schoolbox\\Column\\Binary',     Mapper::TYPE_LOB),
            array('\\Schoolbox\\Column\\Date',       Mapper::TYPE_DATE),
            array('\\Schoolbox\\Column\\DateTime',   Mapper::TYPE_DATETIME),
            array('\\Schoolbox\\Column\\Timestamp',  Mapper::TYPE_TIMESTAMP),
            array('\\Schoolbox\\Column\\Time',       Mapper::TYPE_TIME),
            array('\\Schoolbox\\Column\\Boolean',    Mapper::TYPE_BOOLEAN),
            array('\\Schoolbox\\Column\\Serialized', Mapper::TYPE_SERIALIZED),
            array('\\Schoolbox\\Column\\Decimal',    Mapper::TYPE_DECIMAL),
            array('\\Schoolbox\\Column\\Interval',   Mapper::TYPE_TIMESPAN),
            array(null,                              Mapper::TYPE_NULL),
        );
    }

    /**
     * @param string $expected_class
     * @param string $type
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestBuild')]
    public function testBuild($expected_class = '', $type = '')
    {
        $column = $this->object->build($type);
        if (!$expected_class) {
            $this->assertNull($column);
        } else {
            $this->assertInstanceOf($expected_class, $column);
        }
    }
}
