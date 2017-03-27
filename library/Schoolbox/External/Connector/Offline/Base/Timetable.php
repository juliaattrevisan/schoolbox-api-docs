<?php
/**
 * Offline implementation of timetable connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Offline\Base;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\TimetableInterface;

class Timetable extends Base implements TimetableInterface
{
    /**
     * @inheritdoc
     */
    public function getCurrentSemester($externalId = null)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear($externalId = null)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getLessons($externalIds)
    {
        return new Collection();
    }
}
