<?php
/**
 * Interface for external database events.
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector;

interface ExternalEventsInterface
{
    /**
     * Get events from external database
     *
     * @param  object $user
     * @param  string $startDate
     * @param  string $endDate
     * @return array
     */
    public function getExternalEvents($user, $startDate, $endDate);
}
