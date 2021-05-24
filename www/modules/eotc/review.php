<?php

/**
 * Review
 *
 * Show all the EOTC events to an EOTC Administrator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\EventStatusMapper;

/** @var SitePage $this */

// Redirect to new report
SitePage::localRedir(
    sprintf(
        '/modules/eotc/report/range/%s/?%s',
        Alaress_Date_Format::now()->modify(EventStatusMapper::DEFAULT_FROM_DATE_FOR_REVIEW)->getURLDate(),
        http_build_query([
            'status' => [
                EventStatusMapper::STATUS_DRAFT,
                EventStatusMapper::STATUS_INPRINCIPLE,
                EventStatusMapper::STATUS_DETAILSCOMPLETED,
                EventStatusMapper::STATUS_READYFORFINALAPPROVAL,
                EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL,
            ]
        ])
    )
);
exit;
