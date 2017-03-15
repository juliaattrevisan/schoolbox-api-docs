<?php
/**
 * Offline Scheduler Events stub
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Offline\Base;

use Schoolbox\External\Connector\ExternalEventsInterface;

/**
 * Class ExternalEvents
 *
 * @package Schoolbox\External\Connector\Synergetic\Base
 */
class ExternalEvents implements ExternalEventsInterface
{
    /**
     * @param  object $user
     * @param  string $startDate
     * @param  string $endDate
     * @return array|bool
     */
    public function getExternalEvents($user, $startDate, $endDate)
    {
        return [];
    }
}
