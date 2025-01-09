<?php

/**
 * Edumate implementation of timetable connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Edumate\Base;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ITimetable;

class Timetable extends Base implements ITimetable
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
}
