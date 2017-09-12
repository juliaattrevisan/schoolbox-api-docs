<?php
/**
 * Processed
 *
 * Show approved/cancelled events that are at most 1 month old
 * -or- starting in the future
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
            Alaress_Date_Format::now()->addMonths(-1)->getURLDate(),
            http_build_query([
                'status' => [
                    EventStatusMapper::STATUS_APPROVED,
                    EventStatusMapper::STATUS_REJECTED,
                ]
            ])
        )
    );
    // get from/to date out
    $dateFrom = isset($this->arg[1])
        ? new Alaress_Date_Format($this->arg[1], Alaress_Date_Format::URL_FORMAT)
        : Alaress_Date_Format::now()->addMonths(-1);
    $dateTo   = isset($this->arg[2])
        ? new Alaress_Date_Format($this->arg[2], Alaress_Date_Format::URL_FORMAT)
        : null;

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
