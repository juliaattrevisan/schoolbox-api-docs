<?php

/**
 * ${NAME}
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Config\Loader as ConfigLoader;

/** @var SitePage $this */
$user_mapper = new \Schoolbox\UserMapper();
$user = $user_mapper->findByIdWithFallback($_GET['userId']);
$viewer = $_SESSION['user'];

try {
    Calendar_Layout_Full_Ajax_JSON::permissionCheck($_GET['userId'], $viewer, new Group());
} catch (Exception $e) {
    error($e->getCode());
}

// Calendar generation could take a while
SitePage::extendExecutionTime();

if (isset($_GET['componentInstanceId'])) {
    /* TODO Mine this from the component instance id's config options
     */
    $settings = [
        'eventType',
        'source'
    ];

    /* If componentInstanceId was a querystring param, mine its config options to see what
     * event types were allowed and filter on that result set
     */
    $api = $this->getDi()->get('calendarDashboardApiFactory')->generateFilteredApi($user, $viewer, $settings['source']);
} elseif (isset($_GET['folderId'])) {
    /* If folderId was a querystring param, produce a specific set of subscriptions
     */
    $api = $this->getDi()->get('calendarApiFactory')->produceForFolderId((int)$_GET['folderId'], $user, $viewer);
} else {
    /* Otherwise just get the set of events relevant to a particular user
     */
    $api = $this->getDi()->get('calendarApiFactory')->produceForUser($user, $viewer);
}

/* Find a set of allowed event types by which to filter
 */
$allowedEventTypes = null;

$calendar = new Calendar_Layout_Full_Ajax_JSON(
    $this->getDi()->get('authorizer'),
    $this->getDi()->get('router'),
    $user,
    $viewer,
    $api,
    new ModuleChecker(ConfigLoader::getInstance()),
    $allowedEventTypes
);

$calendar->render();
