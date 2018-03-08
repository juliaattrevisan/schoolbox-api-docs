<?php
/**
 * Render the javascript-based Fullcalendar implementation
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Config\ConfigMapper;

$user_mapper = new \Schoolbox\UserMapper();
$eventMapper = EventMapper::getInstance();

/** @var SitePage $this */

// if ?s=:num, then treat that as a series Id
if (isset($_GET['s']) && is_numeric($_GET['s'])) {
    try {
        $event = $eventMapper->findNextInSeries($_GET['s']);
        $selectedDate = $event->getStartDateTime()->getURLDate();

    } catch (Schoolbox\Mapper\Exception $e) {
        // invalid series ID
        $selectedDate = '';
    }

    // If a series ID was passed, assume this is a notification,
    // and to therefore redirect to day view
    $this->localRedir("/calendar/full/$selectedDate#day");
    exit;
}

/* If both args were provided, assume date and userId respectively
 * Otherwise just assume currently logged in user
 */
if (count($this->arg) == 2) {
    $selectedDate = $this->arg[0];
    try {
        Calendar_Layout_Full_Javascript::permissionCheck($this->arg[1], $_SESSION['user'], new Group());
        $user = $user_mapper->findById($this->arg[1]);
    }
    catch (Exception $e) {
        $this->error($e->getCode());
        exit;
    }
} else {
    $selectedDate = $this->arg[0];
    $user = $_SESSION['user'];
}

/** @var Calendar_Api_Factory $factory */
$factory  = $this->getDi()->get('calendarApiFactory');
$calendar = new Calendar_Layout_Full_Javascript(
    $this,
    $user,
    $selectedDate,
    $_SESSION['user'],
    $factory,
    new ModuleChecker(ConfigMapper::getInstance())
);
$calendar->render();

