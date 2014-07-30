<?php
/**
 * Overrides Aura\Session\SegmentFactory to return Alaress\Session\Segment
 * objects instead.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Alaress\Session;
use Aura\Session\Session;
use Aura\Session\Segment;
use Aura\Session\SegmentFactory as BaseSegmentFactory;

/**
 * A factory to create session segment objects.
 */
class SegmentFactory extends BaseSegmentFactory
{
    /**
     * Creates a session segment object.
     * 
     * @param Session $manager
     * @param string  $name
     *
     * @return Segment
     */
    public function newInstance(Session $manager, $name) {
        if (isset($name)) {
            return new Segment($manager, $name);
        }
        else {
            return new BaseSessionSegment($manager);
        }
    }
}
