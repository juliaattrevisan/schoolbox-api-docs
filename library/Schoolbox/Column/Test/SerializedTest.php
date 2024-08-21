<?php

/**
 * Tests for serialized column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Serialized;

class SerializedTest extends TestCase
{
    /**
     * @var Serialized
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Serialized();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        return array(
            array('b:1;', true),
            array('b:0;', false),
            array('s:3:"123";', "123"),
            array('a:3:{i:0;s:1:"a";i:1;s:1:"b";i:2;s:1:"c";}', array('a', 'b', 'c')),
        );
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return array(
            array(true, 'b:1;'),
            array(false, 'b:0;'),
            array('123', 's:3:"123";'),
            array(array('a', 'b', 'c'), 'a:3:{i:0;s:1:"a";i:1;s:1:"b";i:2;s:1:"c";}'),
        );
    }
}
