<?php

/**
 * TASS Scheduler Events related queries
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @param null|string $productCode If set, build a query for this product
     *                                 code; otherwise, build a query for the
     *                                 user's personal events
     * @param int         $paramSuffix A numeric suffix added to query params,
     *                                 required for disambiguating parameters
     * @return string
     */
    private function buildQueryForProductCode(?string $productCode, int $paramSuffix): string
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
                CASE
                    WHEN ec.start_time IS NOT NULL THEN CAST(ec.start_date + CAST(ec.start_time AS TIME(0)) AS SMALLDATETIME)
                    ELSE CAST(ec.start_date AS SMALLDATETIME)
                END as start,
                CASE
                    WHEN ec.end_time IS NOT NULL THEN CAST(ec.end_date + CAST(ec.end_time AS TIME(0)) AS SMALLDATETIME)
                    ELSE CAST(ec.end_date AS SMALLDATETIME)
                END AS finish,
                ec.campus_code AS location,
                '' AS team
            FROM eventcalendar ec
        ";


        $whereQuery = "
            NOT (
                CASE
                    WHEN ec.end_time IS NOT NULL THEN ec.end_date + CAST(ec.end_time AS TIME(0))
                    ELSE ec.end_date
                END < :startTime{$paramSuffix} OR
                CASE
                    WHEN ec.start_time IS NOT NULL THEN ec.start_date + CAST(ec.start_time AS TIME(0))
                    ELSE ec.start_date
                END >= :endTime{$paramSuffix}
            )
        ";

        /* Personal Events
         */
        if (null === $productCode) {
            return "(
                {$selectQuery}
                WHERE
                    ec.entry_code = :externalId{$paramSuffix} AND
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
                    AND prod_code = :productCode{$paramSuffix}
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
        $end   = new Alaress_Date_Format($endDate, Alaress_Date_Format::TIMESTAMP);

        /* Get personal events for $user - it doesn't matter which role this user is
         */
        $paramSuffix = 0;

        if ($user->getExternalId()) {
            $allEvents[] = $this->buildQueryForProductCode(null, $paramSuffix);
            $params      = $this->buildQueryForProductCodeParams(
                $start,
                $end,
                null,
                $user->getExternalId(),
                $paramSuffix
            );
            $paramSuffix++;
        }

        /* Get role-specific set of events
         */
        if ($user->getRole()->isStudent()) {
            $allEvents[] = $this->buildQueryForProductCode(self::STUDENT_PRODUCT_CODE, $paramSuffix);
            $params      = array_merge(
                $params,
                $this->buildQueryForProductCodeParams(
                    $start,
                    $end,
                    self::STUDENT_PRODUCT_CODE,
                    null,
                    $paramSuffix
                )
            );
        } elseif ($user->getRole()->isStaff()) {
            $allEvents[] = $this->buildQueryForProductCode(self::STAFF_PRODUCT_CODE, $paramSuffix);
            $params      = array_merge(
                $params,
                $this->buildQueryForProductCodeParams(
                    $start,
                    $end,
                    self::STAFF_PRODUCT_CODE,
                    null,
                    $paramSuffix
                )
            );
        } elseif ($user->getRole()->isParent()) {
            $allEvents[] = $this->buildQueryForProductCode(self::PARENT_PRODUCT_CODE, $paramSuffix);
            $params      = array_merge(
                $params,
                $this->buildQueryForProductCodeParams(
                    $start,
                    $end,
                    self::PARENT_PRODUCT_CODE,
                    null,
                    $paramSuffix
                )
            );
        }
        $paramSuffix++;

        $allEvents[] = $this->buildQueryForProductCode(self::PUBLIC_PRODUCT_CODE, $paramSuffix);
        $params      = array_merge(
            $params,
            $this->buildQueryForProductCodeParams(
                $start,
                $end,
                self::PUBLIC_PRODUCT_CODE,
                null,
                $paramSuffix
            )
        );

        /* Collate them into one mega union
         */
        $query = implode(' UNION ', $allEvents);

        return $this->databaseExternal->getQueryIterator($query, $params);
    }

    /**
     * Builds the parameters for the query built by `buildQueryForProductCode`.
     *
     * @param Alaress_Date_Format $start       Fetch all events after this date
     * @param Alaress_Date_Format $end         Fetch all events before this date
     * @param string|null         $productCode The product code, if set
     * @param string|null         $externalId  The user's external ID, if set
     * @param int                 $paramSuffix A numeric suffix added to query
     *                                         params, required for
     *                                         disambiguating parameters
     * @return array
     */
    private function buildQueryForProductCodeParams(
        Alaress_Date_Format $start,
        Alaress_Date_Format $end,
        ?string $productCode,
        ?string $externalId,
        int $paramSuffix
    ): array {
        $params = [
            "startTime{$paramSuffix}" => $start->getDatabase(),
            "endTime{$paramSuffix}"   => $end->getDatabase(),
        ];
        if (isset($productCode)) {
            $params["productCode{$paramSuffix}"] = $productCode;
        }
        if (isset($externalId)) {
            $params["externalId{$paramSuffix}"] = $externalId;
        }
        return $params;
    }
}
