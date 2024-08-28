<?php

/**
 * Test for the boolean column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Boolean as BooleanColumn;

class BooleanTest extends TestCase
{
    /**
     * @var BooleanColumn
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new BooleanColumn();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        return [
            [true, true],
            [false, false],
            [0, false],
            [1, true],
            ['0', false],
            ['00', false],
            ['0.0', false],
            ['0.01', true],
            ['-0.01', true],
            [null, false],
            ['', false],
            [' ', false],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return [
            [true, 1],
            [false, 0],
            [0, 0],
            [1, 1],
            ['0', 0],
            ['00', 0],
            ['0.0', 0],
            ['0.01', 1],
            ['-0.01', 1],
            [null, 0],
            ['', 0],
            [' ', 0],
        ];
    }
}
