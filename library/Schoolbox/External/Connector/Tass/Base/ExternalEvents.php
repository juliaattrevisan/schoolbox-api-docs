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

        /* Anatomy of a query
         */
        $selectQuery = "
            SELECT  
                ec.event_id AS id,
                ec.entry_code AS owner,
                ec.event_name AS comment,
                ec.room_code AS room,
                ecd.cat_desc as type,
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
        ";

        $catdefsQuery = "
            LEFT JOIN eventcatdefs ecd ON ec.cat_num = ecd.cat_num
        ";

        $whereQuery = "
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
        ";

        /* Get personal events for $user - it doesn't matter which role this user is
         */
        $allEvents[] = "(
            {$selectQuery}
            {$catdefsQuery}
            WHERE 
                ec.entry_code = :externalId AND
                {$whereQuery}
                
        )";

        /* Get role-specific set of events
         */
        if ($user->getRole()->isStudent()) {

            /* Get student events via product code TSCPDC
             */
            $allEvents[] = "(
                {$selectQuery}
                INNER JOIN eventtype et ON et.event_id = ec.event_id AND prod_code = 'TSCPDC'
                {$catdefsQuery} 
                WHERE 
                {$whereQuery}
            )";
        }
        elseif ($user->getRole()->isStaff()) {

            /* Get staff events via product code TIAPDC
             */
            $allEvents[] = "(
                {$selectQuery}
                INNER JOIN eventtype et ON et.event_id = ec.event_id AND prod_code = 'TIAPDC'
                {$catdefsQuery} 
                WHERE 
                {$whereQuery}
            )";
        }
        elseif ($user->getRole()->isParent()) {

            /* Get parent events via product code PIAPDC
             */
            $allEvents[] = "(
                {$selectQuery}
                INNER JOIN eventtype et ON et.event_id = ec.event_id AND prod_code = 'PIAPDC'
                {$catdefsQuery} 
                WHERE 
                {$whereQuery}
            )";
        }

        /* Get all the other public events
         */
        $allEvents[] = "(
            {$selectQuery}
            INNER JOIN eventtype et ON et.event_id = ec.event_id AND prod_code = 'PUBPDC'
            {$catdefsQuery} 
            WHERE 
            {$whereQuery}
        )";

        /* Collate them into one mega union
         */
        $query = implode(' UNION ', $allEvents);

        $test = $this->databaseExternal->executeQueryIterator($query, [
            'externalId' => $user->getExternalId(),
            'startTime' => $start->getDatabase(),
            'endTime' => $end->getDatabase()
        ]);

        foreach($test as $row) {
            print_r($row);
        }

//        $test = $this->databaseExternal->executeQueryIterator("SELECT TOP 100 * FROM eventcatdefs");
//
//        foreach($test as $row) {
//            print_r($row);
//        }

//        die();

        return $this->databaseExternal->executeQueryIterator($query, [
            'externalId' => $user->getExternalId(),
            'startTime' => $start->getDatabase(),
            'endTime' => $end->getDatabase()
        ]);
    }
}
