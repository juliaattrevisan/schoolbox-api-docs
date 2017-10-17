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
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\External\Connector\IExternalEvents;
use Schoolbox\External\Connector\Base;
use Schoolbox\User;

class ExternalEvents extends Base implements IExternalEvents
{
    /**
     * @inheritdoc
     */
    public function getExternalEvents(User $user, $startDate, $endDate)
    {
        $start = new Alaress_Date_Format($startDate, Alaress_Date_Format::TIMESTAMP);
        $end = new Alaress_Date_Format($endDate, Alaress_Date_Format::TIMESTAMP);

        /* Get personal events for $user - it doesn't matter which role this user is
         */
        $allEvents[] = "(
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
            where   ec.entry_code = :externalId AND NOT
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
        )";

        /* Known lookups
         */
        $validEventTypes = array_filter(EventTypeMapper::getInstance()->findDistinct('eventType'));

        /* Get role-specific set of events
         */
        if ($user->getRole()->isStudent()) {

            /* Get student events via product code TSCPDC
             */
            $allEvents[] = "(
                SELECT
                    ec.event_id AS id,
                    ec.entry_code AS owner,
                    ec.event_name AS comment,
                    ec.room_code AS room,
                    CASE 
                        WHEN ec.start_time IS NOT NULL THEN ec.start_date + ec.start_time 
                        ELSE ec.start_date 
                    END as start,
                    CASE 
                        WHEN ec.end_time IS NOT NULL THEN ec.end_date + ec.end_time 
                        ELSE ec.end_date 
                    END AS finish,
                    ec.campus_code AS location,
                    '' AS team
                FROM eventcalendar ec
                INNER JOIN eventtype et ON et.event_id = ec.event_id AND prod_code = 'TSCPDC'
                WHERE 
                    NOT (
                        CASE 
                            WHEN ec.end_time IS NOT NULL THEN ec.end_date + ec.end_time 
                            ELSE ec.end_date 
                        END < :startTime OR
                        CASE 
                            WHEN ec.start_time IS NOT NULL THEN ec.start_date + ec.start_time 
                            ELSE ec.start_date 
                        END >= :endTime 
                    )
            )";
        }
        elseif ($user->getRole()->isStaff()) {

            /* Get staff events via product code TIAPDC
             */
            $allEvents[] = "(
            )";
        }
        elseif ($user->getRole()->isParent()) {

            /* Get parent events via product code PIAPDC
             */
            $allEvents[] = "(
            )";
        }

        /* Get all the other public events
         */
//        $allEvents[] = "(
//        )";

        /* Collate them into one mega union
         */
        $query = implode(' UNION ', $allEvents);

        /* Explore the db
         */
        $test = $this->databaseExternal->executeQueryIterator($query, [
            'externalId' => $user->getExternalId(),
            'startTime' => $start->getDatabase(),
            'endTime' => $end->getDatabase()
        ]);

        foreach($test as $row) {
            print_r($row);
        }
die();
//        $test = $this->databaseExternal->executeQueryIterator("SELECT TOP 100 * FROM eventtype");
//
//        foreach($test as $row) {
//            print_r($row);
//        }
//
//        print_r($query);
//        die();

        return $this->databaseExternal->executeQueryIterator($query, [
            'externalId' => $user->getExternalId(),
            'startTime' => $start->getDatabase(),
            'endTime' => $end->getDatabase()
        ]);
    }
}
