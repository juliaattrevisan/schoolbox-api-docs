<?php

/**
 * Tests for base column type - its hydrator and dehydrator don't change
 * anything, besides coercing all values to UTF-8 on dehydrate.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Base;

class BaseTest extends TestCase
{

    /**
     * @var Base
     */
    protected $object;

    public function setupObject()
    {
        $this->object = new Base();
    }

    public static function getTestHydrate()
    {
        return array(
            array(true, true),
            array(false, false),
            array(null, null),
            array('Dumb', 'Dumb'),
        );
    }

    public static function getTestDehydrate()
    {
        return array(
            array(true, '1'),
            array(false, ''),
            array(null, null),
            array('Dumb', 'Dumb'),
            // invalid UTF8 sequence - gets replaced with placeholder
            array('12' . chr(243) . '34', '12?34'),
        );
    }
}
