<?php

/**
 * Test for
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Boolean;

class BooleanTest extends TestCase
{

    /**
     * @var Boolean
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Boolean();
    }

    /**
     * @inheritdoc
     */
    public function getTestHydrate()
    {
        return array(
            array(true, true),
            array(false, false),
            array(0, false),
            array(1, true),
            array('0', false),
            array('00', false),
            array('0.0', false),
            array('0.01', true),
            array('-0.01', true),
            array(null, false),
            array('', false),
            array(' ', false),
        );
    }

    /**
     * @inheritdoc
     */
    public function getTestDehydrate()
    {
        return array(
            array(true, 1),
            array(false, 0),
            array(0, 0),
            array(1, 1),
            array('0', 0),
            array('00', 0),
            array('0.0', 0),
            array('0.01', 1),
            array('-0.01', 1),
            array(null, 0),
            array('', 0),
            array(' ', 0),
        );
    }
}
