<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/** @var SitePage $this */

// don't bother trying if filters isn't set
if (!isset($_POST['filters'])) {
    http_response_code(400);
    die('Not enough data supplied');
}

$_SESSION['calendarFilter'] = [
    'campus'    => [],
    'eventType' => [],
    'group'     => [],
    'source'    => [],
];
foreach ($_POST['filters'] as $filterType => $filterValues) {
    switch ($filterType) {
        case 'campus':
        case 'eventType':
        case 'group':
            $_SESSION['calendarFilter'][$filterType] = array_map('intval', $filterValues);
            break;

        case 'source':
            $_SESSION['calendarFilter'][$filterType] = [];
            foreach ($filterValues as $filterValue) {
                $_SESSION['calendarFilter'][$filterType][] = array_map('intval', $filterValue);
            };
            break;
    }
}

$save = session_write_close();
$jsonOut = ['msg' => "Updated successfully!", 'cal' => $_SESSION['calendarFilter'], 'save' => $save];
$this->outputJson($jsonOut, false, 200);
die();
