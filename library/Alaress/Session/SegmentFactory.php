<?php

/**
 * Overrides Aura\Session\SegmentFactory to return Alaress\Session\Segment
 * objects instead.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Session;

use Aura\Session\Session as BaseSession;
use Aura\Session\Segment as BaseSegment;
use Aura\Session\SegmentFactory as BaseSegmentFactory;

/**
 * A factory to create session segment objects.
 */
class SegmentFactory extends BaseSegmentFactory
{
    /**
     * Creates a session segment object.
     *
     * @param BaseSession $manager
     * @param string      $name
     *
     * @return BaseSegment
     */
    public function newInstance(BaseSession $manager, $name)
    {
        if (isset($name)) {
            return new BaseSegment($manager, $name);
        } else {
            return new BaseSessionSegment($manager);
        }
    }
}
