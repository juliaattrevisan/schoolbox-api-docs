<?php

/**
 * Test for the boolean enum column type.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\BoolEnum;

class BoolEnumTest extends TestCase
{
    /**
     * @var BoolEnum
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new BoolEnum();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        return [
            ['true', true],
            ['false', false],
            [null, false],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return [
            [true, 'true'],
            [false, 'false'],
            [1, 'true'],
            [0, 'false'],
            [null, 'false'],
        ];
    }
}
