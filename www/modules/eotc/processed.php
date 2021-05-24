<?php

/**
 * Processed
 *
 * Show approved/cancelled events that are at most 1 month old
 * -or- starting in the future
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\EventStatusMapper;

/** @var SitePage $this */

// Redirect to new report
/*
 * Report types:
 * /range/{from}/{to}: show a list of approved, rejected events between from, to
 * /:                  /range/{-1 month}/{now}
 * /all:               show all future events that are approved/rejected
 */
$timespanType = isset($this->arg[0]) ? $this->arg[0] : 'range';
if ($timespanType === 'range') {
    SitePage::localRedir(
        sprintf(
            '/modules/eotc/report/range/%s/?%s',
            Alaress_Date_Format::now()->modify(EventStatusMapper::DEFAULT_FROM_DATE_FOR_PROCESSED)->getURLDate(),
            http_build_query([
                'status' => [
                    EventStatusMapper::STATUS_APPROVED,
                    EventStatusMapper::STATUS_REJECTED,
                ]
            ])
        )
    );
} elseif ($timespanType === 'all') {
    /* Show paginated events, ordered by datetime, till the
     * beginning of history
     */
    SitePage::localRedir(
        sprintf(
            '/modules/eotc/report/all/?%s',
            http_build_query([
                'status' => [
                    EventStatusMapper::STATUS_APPROVED,
                    EventStatusMapper::STATUS_REJECTED,
                ]
            ])
        )
    );
}
