<?php

/**
 * ${NAME}
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Calendar\Component\Dashboard\FilterCodec;
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

/* Find a set of allowed event types by which to filter
 */
$allowedEventTypes = null;

/* TODO Maybe able to witness another roletype's dashboard payload
 *  If you dig hard enough you could perhaps illegally witness a payload belonging to a different roletype's
 *  dashboard configuration...you could lock that down further by ensuring that $_GET['componentInstanceId']
 *  belongs on a dashboard corresponding to $viewer.
 */
if (isset($_GET['componentInstanceId']) && (int)$_GET['componentInstanceId']) {
    /* This particular method gives me the configs that I can use
     */
    $instance = Component_InstanceMapper::getInstance()->findByInstanceId((int)$_GET['componentInstanceId']);

    /* No instance? No trabajo
     *
     * This might happen if you partially sync from live to dev when both environments are very different
     * causing a dev environment to look for data that doesn't exist; bad update or broken data might
     * also cause this kind of scenario. Just exit with an empty payload in that circumstance
     */
    if (!$instance) {
        echo '[]';
        exit();
    }

    /* If you can't decode the config, use a default struct
     */
    $settings = (new FilterCodec())->decode($instance->getConfigByKey('calendarFilter')->getValue());

    /* Figure out the allowed event types as well; compile them into a stack
     */
    $allowedEventTypes = $settings['eventType'];

    /* If componentInstanceId was a querystring param, mine its config options to see what
     * event types were allowed and filter on that result set
     */
    $api = $this->getDi()->get('calendarDashboardApiFactory')->produceFilteredApi($user, $viewer, $settings['source']);
} elseif (isset($_GET['folderId'])) {
    /* If folderId was a querystring param, produce a specific set of subscriptions
     */
    $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById((int)$_GET['folderId']);

    $api = $this->getDi()->get('calendarApiFactory')->produceForFolder($folder, $user, $viewer);
} elseif (isset($_GET['timetableCalendar'])) {
    /* These are hardcoded subscriptions that are somehow Timetable-related
     * Includes both SUDS Timetable and all the live connector ones
     */
    $api = $this->getDi()->get('calendarApiFactory')->produceForTimetableCalendar($user, $viewer);
} else {
    /* Otherwise just get the set of events relevant to a particular user
     */
    $api = $this->getDi()->get('calendarApiFactory')->produceForUser($user, $viewer);
}

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
