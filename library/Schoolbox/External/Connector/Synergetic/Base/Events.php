<?php
/**
 * Synergetic Community Portal related queries
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 *
 * @specialThanksTo Damian Sloane <damian.sloane@alaress.com.au>
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress\DebugBar\Instance as DebugBar;
use MSSQL;
use Schoolbox\External\Connector\CommunityPortalInterface;
use UnexpectedValueException;

class ExternalEvents implements ExternalEventsInterface
{
    /**
     * @var MSSQL
     */
    private $database;


    /**
     * Constructor
     *
     * @param string $key
     * @param string $initializationVector
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
        // Figure out the current year and semester
        $externalUser = new \External_User();
    }


    /**
     * Updates the community portal password for this user.
     *
     * @param  string $communityId
     * @param  string $newPassword
     * @return bool
     * @throws UnexpectedValueException if either key or IV was not set
     */
    public function getExternalEvents($user, $startDate, $endDate)
    {
        $query = "
SELECT
  Events.EventCode                  AS code,
  Events.Description                AS title,
  Events.Comment                    AS description
FROM SynergyOne.dbo.Events
WHERE Events.EventDate > '2017-01-01 00:00:00' AND Events.EventEndDate < '2017-03-03 00:00:00'
ORDER BY Events.EventCode ASC
        ";
        $this->database->query($query);
        new \Alaress_Probe($this->getDatabase()->getAll($query));
        return $this->getDatabase()->getAll($query);
    }

 }
