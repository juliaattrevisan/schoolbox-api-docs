<?php

/**
 * Edumate implementation of external events
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Edumate\Base;

use InvalidArgumentException;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\IExternalEvents;
use Schoolbox\User;

class ExternalEvents extends Base implements IExternalEvents
{
    public function getExternalEvents(User $user, $startDate, $endDate)
    {
        if (empty($user->getExternalId())) {
            throw new InvalidArgumentException("External ID is not a Number");
        }

        $query = 'SELECT
                    EVENT_ID AS "id",
                    TITLE AS "title",
                    COMMENT AS "comment",
                    OWNER AS "owner",
                    ROOM AS "room",
                    TYPE AS "type",
                    START_DATE AS "start",
                    END_DATE AS "finish",
                    LOCATION AS "location"
                FROM SCHOOLBOX_EVENT
                WHERE
                    OWNER = :externalId
                    AND START_DATE >= :startDate
                    AND END_DATE <= :endDate';

        return $this->databaseExternal->getQueryIterator(
            $query,
            [
             'externalId' => $user->getExternalId(),
             'startDate'  => date('Y-m-d', $startDate),
             'endDate'    => date('Y-m-d', $endDate)
            ]
        );
    }
}
