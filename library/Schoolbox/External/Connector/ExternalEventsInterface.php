<?php
/**
 * Interface for external database community portal classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Schoolbox\External\Normalise\User;

interface ExternalEventsInterface
{
    /**
     * Get events from external database
     *
     * @param  string $startDate
     * @param  string $endDate
     * @return array
     */
    public function getExternalEvents($startDate, $endDate);
}
