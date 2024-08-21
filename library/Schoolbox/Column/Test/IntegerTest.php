<?php

/**
 * Tests for integer column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Integer as IntegerColumn;

class IntegerTest extends TestCase
{

    /**
     * @var IntegerColumn
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new IntegerColumn();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        return array(
            array(true, null),
            array(false, null),
            array(null, null),
            array('q', null),
            array('O', null),
            array('Not an integer', null),
            array(array(), null),
            array('123', 123),
            array('1.53e3', 1530),
            array(0xFF, 255),
            array(077, 63),
            array(-1000, -1000),
            array(-1000, -1000),
            array(-1000.1, -1000),
            array(-1000.98, -1000),
            array(PHP_INT_MAX, PHP_INT_MAX),
        );
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return array(
            array(true, null),
            array(false, null),
            array(null, null),
            array('q', null),
            array('O', null),
            array('Not an integer', null),
            array(array(), null),
            array('123', 123),
            array('1.53e3', 1530),
            array(0xFF, 255),
            array(077, 63),
            array(-1000, -1000),
            array(-1000, -1000),
            array(-1000.1, -1000),
            array(-1000.98, -1000),
            array(PHP_INT_MAX, PHP_INT_MAX),
        );
    }
}
