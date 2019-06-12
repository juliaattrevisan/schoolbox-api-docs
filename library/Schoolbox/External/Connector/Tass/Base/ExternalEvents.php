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
use InvalidArgumentException;
use Schoolbox\External\Connector\IExternalEvents;
use Schoolbox\External\Connector\Base;
use Schoolbox\User;

class ExternalEvents extends Base implements IExternalEvents
{

    /**
     * Student product code
     */
    private const STUDENT_PRODUCT_CODE = 'TSCPDC';

    /**
     * Staff product code
     */
    private const STAFF_PRODUCT_CODE = 'TIAPDC';

    /**
     * Parent product code
     */
    private const PARENT_PRODUCT_CODE = 'PIAPDC';

    /**
     * Public product code
     */
    private const PUBLIC_PRODUCT_CODE = 'PUBPDC';

    /**
     * Build a query to get events for a given $productCode
     * If $productCode is null, obtain personal events instead
     *
     * @param null|string $productCode
     * @return string
     */
    private function buildQueryForProductCode(?string $productCode): string
    {

        /* STUFF()
         * Inject a string into another string
         * https://www.w3schools.com/sql/func_sqlserver_stuff.asp
         *
         * James and I decided that we could concat the multiple event types assigned in TASS using STUFF().
         * This creates a comma-separated representation of multiple event types e.g. "Sporting, General, Academic".
         * This will never match to any schoolbox event type and will fall into the default type set in admin.
         * The default type is specified in admin > calendar > subscribe > external db.
         *
         * Anyone using this connector as originally designed should remain unaffected ie. BTAC Tim Gardiner.
         * That is, anyone importing events with one event type.
         *
         * Schoolbox doesn't support multiple event types and even if we wanted to do that, the task is enormous.
         * All the other variants of aggregation in MSSQL are all really fiddly and prevent you from doing what we need.
         */

        /* Anatomy of a query
         */
        $selectQuery = "
            SELECT  
                ec.event_id AS id,
                ec.entry_code AS owner,
                ec.event_name AS title,
                ec.event_desc AS comment,
                ec.room_code AS room,
                LTRIM(RTRIM(STUFF((SELECT ', ' + LTRIM(RTRIM(ecd.cat_desc))
            FROM eventcatdefs ecd WHERE ec.cat_num = ecd.cat_num FOR XML PATH('')), 1 , 1 , ''))) AS type,
ec.start_date, ec.start_time,
                CASE 
                    WHEN ec.start_time IS NOT NULL THEN ec.start_date + CAST(ec.start_time AS TIME(0))
                    ELSE ec.start_date 
                END as start,
ec.start_date + ec.start_time AS startdateplusstarttime,                
ec.end_date, ec.end_time,
                CASE 
                    WHEN ec.end_time IS NOT NULL THEN ec.end_date + CAST(ec.end_time AS TIME(0))
                    ELSE ec.end_date 
                END AS finish,
ec.end_date + ec.end_time AS enddateplusendtime,
                ec.campus_code AS location,
                '' AS team
            FROM eventcalendar ec
        ";


        $whereQuery = '
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
        ';

        /* Personal Events
         */
        if (null === $productCode) {
            return "(
                {$selectQuery}
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
                WHERE 
                {$whereQuery}
            )";
    }


    /**
     * @inheritdoc
     * @throws InvalidArgumentException
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
