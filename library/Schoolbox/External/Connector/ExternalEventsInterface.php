<?php
/**
 * Interface for external database events.
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector;

use Alaress\Database\Connector\IConnector;
use Schoolbox\IConfigLoader;

interface ExternalEventsInterface
{
    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param array|IConnector $databaseExternal External database connector(s)
     * @param TimetableInterface $timetableExternal External timetable connector
     */
    function __construct($config, $databaseExternal, $timetableExternal);

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
