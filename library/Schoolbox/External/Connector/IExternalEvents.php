<?php

/**
 * Interface for external database events.
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector;

use Alaress\Database\Iterator\IIterator;
use Schoolbox\User;

interface IExternalEvents
{
    /**
     * Get events from external database
     *
     * @param User $user
     * @param string $startDate
     * @param string $endDate
     * @return IIterator
     */
    public function getExternalEvents(User $user, $startDate, $endDate);
}
