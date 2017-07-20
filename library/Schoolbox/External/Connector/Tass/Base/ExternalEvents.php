<?php
/**
 * TASS Scheduler Events related queries
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Alaress_Date_Format;
use Schoolbox\User;
use Schoolbox\External\Connector\IExternalEvents;
use Schoolbox\External\Connector\Base;

class ExternalEvents extends Base implements IExternalEvents
{
    /**
     * @inheritdoc
     */
    public function getExternalEvents(User $user, $startDate, $endDate)
    {
        $start = new Alaress_Date_Format($startDate, Alaress_Date_Format::TIMESTAMP);
        $end = new Alaress_Date_Format($endDate, Alaress_Date_Format::TIMESTAMP);

        $query = "
            select  ec.event_id as id,
                    ec.entry_code as owner,
                    ec.event_name as comment,
                    ec.room_code as room,
                    case 
                        when ec.start_time is not null then ec.start_date + ec.start_time 
                        else ec.start_date 
                    end as start,
                    case 
                        when ec.end_time is not null then ec.end_date + ec.end_time 
                        else ec.end_date 
                    end as finish,
                    ec.campus_code as location,
                    '' as team
            from    eventcalendar ec
            where   ec.entry_code = :staffExternalId AND NOT
                    (
                        CASE 
                            WHEN ec.end_time IS NOT NULL THEN ec.end_date + ec.end_time 
                            ELSE ec.end_date 
                        END < :startTime OR
                        CASE 
                            WHEN ec.start_time IS NOT NULL THEN ec.start_date + ec.start_time 
                            ELSE ec.start_date 
                        END >= :endTime 
                    )
        ";

        return $this->databaseExternal->executeQueryIterator($query, [
            'staffExternalId' => $user->getExternalId(),
            'startTime' => $start->getDatabase(),
            'endTime' => $end->getDatabase()
        ]);
    }
}
