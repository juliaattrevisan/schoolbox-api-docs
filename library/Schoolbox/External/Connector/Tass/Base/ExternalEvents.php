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
     * Student product code
     */
    const STUDENT_PRODUCT_CODE = 'TSCPDC';

    /**
     * Staff product code
     */
    const STAFF_PRODUCT_CODE = 'TIAPDC';

    /**
     * Parent product code
     */
    const PARENT_PRODUCT_CODE = 'PIAPDC';

    /**
     * Public product code
     */
    const PUBLIC_PRODUCT_CODE = 'PUBPDC';

    /**
     * Build a query to get events for a given $productCode
     * If $productCode is null, obtain personal events instead
     *
     * @param $productCode
     * @return string
     */
    private function buildQueryForProductCode($productCode)
    {
        /* Anatomy of a query
         */
        $selectQuery = "
            SELECT  
                ec.event_id AS id,
                ec.entry_code AS owner,
                ec.event_name AS title,
                ec.event_desc AS comment,
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

        /* Personal Events
         */
        if ($productCode == null) {
            return "(
                {$selectQuery}
                {$catdefsQuery}
                WHERE 
                    ec.entry_code = :externalId AND
                    {$whereQuery}
            )";
        }

        /* Other events for a particular productCode
         */
        return "(
                {$selectQuery}
                INNER JOIN eventtype et 
                    ON et.event_id = ec.event_id 
                    AND ec.cmpy_code = et.cmpy_code
                    AND prod_code = '{$productCode}'
                {$catdefsQuery} 
                WHERE 
                {$whereQuery}
            )";
    }

    /**
     * @inheritdoc
     */
    public function getExternalEvents(User $user, $startDate, $endDate)
    {
        $start = new Alaress_Date_Format($startDate, Alaress_Date_Format::TIMESTAMP);
        $end = new Alaress_Date_Format($endDate, Alaress_Date_Format::TIMESTAMP);

        /* Get personal events for $user - it doesn't matter which role this user is
         */
        $allEvents[] = $this->buildQueryForProductCode(null);

        /* Get role-specific set of events
         */
        if ($user->getRole()->isStudent()) {
            $allEvents[] = $this->buildQueryForProductCode(self::STUDENT_PRODUCT_CODE);
        } elseif ($user->getRole()->isStaff()) {
            $allEvents[] = $this->buildQueryForProductCode(self::STAFF_PRODUCT_CODE);
        } elseif ($user->getRole()->isParent()) {
            $allEvents[] = $this->buildQueryForProductCode(self::PARENT_PRODUCT_CODE);
        }

        $allEvents[] = $this->buildQueryForProductCode(self::PUBLIC_PRODUCT_CODE);

        /* Collate them into one mega union
         */
        $query = implode(' UNION ', $allEvents);

        return $this->databaseExternal->getQueryIterator($query, [
            'externalId' => $user->getExternalId(),
            'startTime' => $start->getDatabase(),
            'endTime' => $end->getDatabase()
        ]);
    }
}
