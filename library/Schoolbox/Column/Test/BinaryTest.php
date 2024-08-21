<?php

/**
 * Tests for binary column type - its hydrator and dehydrator don't change
 * anything.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Base;
use Schoolbox\Column\Binary;

class BinaryTest extends TestCase
{

    /**
     * @var Binary
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Binary();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        return array(
            array(true, true),
            array(false, false),
            array(null, null),
            array('Dumb', 'Dumb'),
        );
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return array(
            array(true, true),
            array(false, false),
            array(null, null),
            array('Dumb', 'Dumb'),
            array('12' . chr(128) . '34', '12' . chr(128) . '34'),
        );
    }
}
