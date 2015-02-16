<?php
/**
 * THIS SCRIPT IS TO TEST THAT EVENT NOTIFICATIONS ARE SENT TO THE RIGHT PEOPLE [SB-6927]
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventNotifier;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

const PETER_RHODES  = 74;
const JO_RHODES     = 75;
const LISA_RHODES   = 206;
const RACHEL_RHODES = 229;
const DAVID_RHODES  = 230;

/** @var User $alaressAdmin */
$alaressAdmin = UserMapper::getInstance()->findById(19);

// ensure the rhodes' family emails are set correctly
Database::getInstance()->exec("
    UPDATE `user`
    SET local_email = FALSE, alt_email = LOWER(CONCAT(firstname, '.', lastname, '@schoobox.com.au'))
    WHERE lastname = 'Rhodes'
");

// delete event(s) from previous run(s)
Database::getInstance()->exec("DELETE FROM `event` WHERE title LIKE '[SB-6927]%'");

// override permissions to 300
Database::getInstance()->exec("
    UPDATE msg_route_perm rp
    SET min_level = 300, max_level = 300, default_level = 300
    WHERE rp.`msg_source_id` IN (
      SELECT id
      FROM msg_source
      WHERE namespace = 6
    )
");

$campi = [ 'SEN' ];
$roles = [ 3 ];

// set event to start 10 minutes and 5 seconds from now
$event = new Event();
$event->setStartDateTime(new Alaress_Date_Format(time() + (EventNotifier::UPCOMING_WINDOW * 60) + 5, 'U'));
$event->setEndDateTime($event->getStartDateTime());
$event->setAccess(Event::ACCESS_CAMPUS);
$event->setCampusCollection(CampusMapper::getInstance()->findByColumn('ext_timetable_code', $campi));
$event->setRoleCollection(RoleMapper::getInstance()->findByColumn('id', $roles));
$event->setType(EventTypeMapper::getInstance()->findById(10));
$event->setOwner($alaressAdmin);
$event->setAuthor($alaressAdmin);
$event->setAttendance(null);

$hash = substr(sha1(spl_object_hash($event)), 0, 8);

// get a hash of...uh...yourself
$event->setTitle("[SB-6927] Test Event: $hash");

// aaaaaand, go
$emap = EventMapper::getInstance();
$emap->setSessionData(['user' => $alaressAdmin]);
$emap->save($event);