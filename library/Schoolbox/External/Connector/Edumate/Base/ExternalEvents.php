<?php

/**
 * Edumate implementation of external events
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Edumate\Base;

use Alaress\Database\Iterator\Base as Iterator;
use Schoolbox\User;

class ExternalEvents extends \Schoolbox\External\Connector\Offline\Base\ExternalEvents
{
    public function getExternalEvents(User $user, $startDate, $endDate)
    {
        if(empty($user->getExternalId())) {
            return parent::getExternalEvents($user, $startDate, $endDate);
        }

        if($user->getRole()->isStudent()) {
            $query = '
                SELECT
                    EVENT.EVENT_ID AS "id",
                    EVENT.EVENT AS "title",
                    EVENT.DESCRIPTION AS "comment",
                    STUDENT.STUDENT_NUMBER AS "owner",
                    ROOM.ROOM AS "room",
                    EVENT_TYPE.EVENT_TYPE AS "type",
                    EVENT.START_DATE AS "start",
                    EVENT.END_DATE AS "finish",
                    EVENT.LOCATION AS "location"
                FROM
                    EVENT
                    INNER JOIN EVENT_STUDENT ON EVENT.EVENT_ID = EVENT_STUDENT.EVENT_ID
                    INNER JOIN STUDENT ON EVENT_STUDENT.EVENT_STUDENT_ID = STUDENT.STUDENT_ID
                    INNER JOIN EVENT_TYPE ON EVENT.EVENT_TYPE_ID = EVENT_TYPE.EVENT_TYPE_ID
                    LEFT JOIN EVENT_ROOM ON EVENT_ROOM.EVENT_ID = EVENT.EVENT_ID
                    LEFT JOIN ROOM ON EVENT_ROOM.ROOM_ID = ROOM.ROOM_ID
                WHERE EVENT.PUBLISH_FLAG = 1
                        AND STUDENT.STUDENT_NUMBER = :externalId
                        AND EVENT.START_DATE <= :startDate
                        AND EVENT.END_DATE >= :endDate
            ';
        }
        elseif ($user->getRole()->isStaff()) {
            $query = '
                SELECT
                    EVENT.EVENT_ID AS "id",
                    EVENT.EVENT AS "title",
                    EVENT.DESCRIPTION AS "comment",
                    STAFF.STAFF_NUMBER AS "owner",
                    ROOM.ROOM AS "room",
                    EVENT_TYPE.EVENT_TYPE AS "type",
                    EVENT.START_DATE AS "start",
                    EVENT.END_DATE AS "finish",
                    EVENT.LOCATION AS "location"
                FROM
                    EVENT
                    INNER JOIN EVENT_STAFF ON EVENT.EVENT_ID = EVENT_STAFF.EVENT_ID AND EVENT_STAFF.PRIMARY_FLAG = 1
                    INNER JOIN STAFF ON EVENT_STAFF.STAFF_ID = STAFF.STAFF_ID
                    INNER JOIN EVENT_TYPE ON EVENT.EVENT_TYPE_ID = EVENT_TYPE.EVENT_TYPE_ID
                    LEFT JOIN EVENT_ROOM ON EVENT_ROOM.EVENT_ID = EVENT.EVENT_ID
                    LEFT JOIN ROOM ON EVENT_ROOM.ROOM_ID = ROOM.ROOM_ID
                WHERE EVENT.PUBLISH_FLAG = 1
                    AND STAFF.STAFF_NUMBER = :externalId
                    AND EVENT.START_DATE <= :startDate
                    AND EVENT.END_DATE >= :endDate
            ';

        }
        else {
            return parent::getExternalEvents($user, $startDate, $endDate);
        }

        return $this->databaseExternal->getQueryIterator(
            $query,
            [
                'externalId' => $user->getExternalId(),
                'startDate'  => date('Y-m-d', $startDate),
                'endDate'    => date('Y-m-d', $endDate),
            ]
        );
    }
}
