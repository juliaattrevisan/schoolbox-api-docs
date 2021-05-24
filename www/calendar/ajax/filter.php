<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// don't bother trying if filters isn't set
if (!isset($_POST['filters'])) {
    http_response_code(400);
    die('Not enough data supplied');
}

// this is just to properly initialise the sessions so changes will take hold
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$calHandler = new Calendar();

//$response = $calHandler->updateFilters($_POST['fList'], $_POST['view']);
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

die('Updated successfully');
