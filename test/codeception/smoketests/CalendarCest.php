<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;
use Schoolbox\Calendar;

class CalendarCest
{

    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }


    /** Create a personal event from the full calendar month view, using a curl post.
     *
     * @param IntegrationTester $i
     */
    public function createEventFullCalendar(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $admin     = $i->grabUser('alaress');
        $title     = 'Test Event, personal create from calendar at ' . $timestamp;

        #WHEN
        $createEventRequest = new Payload\Base('POST', '/calendar/full', '/calendar/ajax/create');
        $createEventRequest->setFormParam('userId', $admin->getId());
        $createEventRequest->setFormParam('allDay', 1);
        $createEventRequest->setFormParam('date', date('d-m-Y'));
        $createEventRequest->setFormParam('title', $title);
        $createEventRequest->setFormParam('type', Calendar\Event::ACCESS_PERSONAL);

        $i->amGoingTo('Send request to create calendar event on full calendar page');
        $i->sendRequest($createEventRequest);

        #THEN
        $i->seeResponseCodeIs(200);
    }


    /**
     * Create a personal event from the calendar event modify page.
     *
     * @param IntegrationTester $i
     */
    public function createPersonalEvent(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $admin     = $i->grabUser('alaress');
        $title     = 'Test Event, personal create from modify event at ' . $timestamp;

        #WHEN
        $createEventRequest = new Payload\Calendar\Event\Create($admin->getId(), $admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Calendar\Event::ACCESS_PERSONAL);
        $createEventRequest->setFormParam('campus[0]', 1);
        $createEventRequest->setFormParam('type', 1);
        $i->amGoingTo('Send request to create personal calendar event');
        $i->sendRequest($createEventRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $location = 'https://vagrant.dev.schoolbox.cloud/calendar/full/' . date('d-m-Y', $timestamp) . '/' . $admin->getId();
        $i->seeHttpHeader('location', $location);
    }


    /** Create a group event from the calendar event modify page.
     *
     * @param IntegrationTester $i
     */
    public function createGroupEvent(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp   = time();
        $admin       = $i->grabUser('alaress');
        $title       = 'Test Event, group create from modify event at ' . $timestamp;
        $targetGroup = $i->grabFirstHomepageIdByTitle('Basketball');

        #WHEN
        $createEventRequest = new Payload\Calendar\Event\Create($admin->getId(), $admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Calendar\Event::ACCESS_GROUP);
        $createEventRequest->setFormParam('group[0]', $targetGroup);
        $createEventRequest->setFormParam('campus[0]', 1);
        $createEventRequest->setFormParam('type', 1);
        $i->amGoingTo('Send request to create group calendar event');
        $i->sendRequest($createEventRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $location = 'https://vagrant.dev.schoolbox.cloud/calendar/full/' . date('d-m-Y', $timestamp) . '/' . $admin->getId();
        $i->seeHttpHeader('location', $location);
    }


    /** Create a campus event from the calendar event modify page.
     *
     * @param IntegrationTester              $i
     */
    public function createCampusEvent(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $admin          = $i->grabUser('alaress');
        $title          = 'Test Event, campus create from modify event at ' . $timestamp;
        $targetCampuses = [$i->grabCampusByName('Secondary')->getId(), $i->grabCampusByName('Primary')->getId()];
        $targetRoles    = [$i->grabRoleByTitle('Administration')->getId(), $i->grabRoleByTitle('Teacher')->getId()];

        #WHEN
        $createEventRequest = new Payload\Calendar\Event\Create($admin->getId(), $admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Calendar\Event::ACCESS_CAMPUS);
        $createEventRequest->setFormParam('campus[0]', $targetCampuses[0]);
        $createEventRequest->setFormParam('campus[1]', $targetCampuses[1]);
        $createEventRequest->setFormParam('roles[0]', $targetRoles[0]);
        $createEventRequest->setFormParam('roles[1]', $targetRoles[1]);
        $createEventRequest->setFormParam('type', 1);
        $i->amGoingTo('Send request to create campus calendar event');
        $i->sendRequest($createEventRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $location = 'https://vagrant.dev.schoolbox.cloud/calendar/full/' . date('d-m-Y', $timestamp) . '/' . $admin->getId();
        $i->seeHttpHeader('location', $location);
    }


    /** Modify personal event using the calendar event edit page.
     *
     * @param IntegrationTester              $i
     */
    public function modifyPersonalEvent(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $admin     = $i->grabUser('alaress');
        $title     = 'Test Event, personal modify at ' . $timestamp;

        $eventPayload = new Payload\Calendar\Event\Create($admin->getId(), $admin->getId());
        $eventPayload->setTitle($title);
        $eventPayload->setFormParam('level', Calendar\Event::ACCESS_PERSONAL);
        $eventPayload->setFormParam('campus[0]', 1);
        $eventPayload->setFormParam('type', 1);
        $i->amGoingTo('Send request to create personal calendar event');
        $i->sendRequest($eventPayload);
        $i->seeResponseCodeIs(302);

        $event = $i->grabCalendarEventByTitle($title);

        #WHEN
        $eventPayload = new Payload\Calendar\Event\Update($event);
        $eventPayload->setFormParam('detail', '<p> I have been updated </p>');
        $i->amGoingTo('Send request to modify personal calendar event');
        $i->sendRequest($eventPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', 'https://vagrant.dev.schoolbox.cloud/calendar/' . date('d-m-Y', $timestamp));
    }


    /** RSVP to a calendar event. Curl post
     *
     * @param IntegrationTester $i
     */
    public function rsvpToEvent(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $admin          = $i->grabUser('alaress');
        $title          = 'Test Event, rsvp campus event at ' . $timestamp;
        $targetCampuses = [$i->grabCampusByName('Secondary')->getId(), $i->grabCampusByName('Primary')->getId()];
        $targetRoles    = [$i->grabRoleByTitle('Administration')->getId(), $i->grabRoleByTitle('Teacher')->getId()];

        $createEventRequest = new Payload\Calendar\Event\Create($admin->getId(), $admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Calendar\Event::ACCESS_CAMPUS);
        $createEventRequest->setFormParam('type', 1);
        $createEventRequest->setFormParam('campus[0]', $targetCampuses[0]);
        $createEventRequest->setFormParam('campus[1]', $targetCampuses[1]);
        $createEventRequest->setFormParam('roles[0]', $targetRoles[0]);
        $createEventRequest->setFormParam('roles[1]', $targetRoles[1]);
        $createEventRequest->setFormParam('attendance', 1);

        $i->amGoingTo('Send request to create campus calendar event with attendance');
        $i->sendRequest($createEventRequest);

        #WHEN
        $event        = $i->grabCalendarEventByTitle($title);
        $RSVP_Payload = new Payload\Base('POST', '/calendar/full/' . date('d-m-Y', $timestamp), '/service/attendance/setAttendance');
        $RSVP_Payload->setFormParam('event[id]', $event->getId());
        $RSVP_Payload->setFormParam('status', 'add');

        $i->amGoingTo('Send request to RSVP to campus calendar event.');
        $i->sendRequest($RSVP_Payload);

        #THEN
        $i->seeResponseCodeIs(200);
    }


    /**
     * Approve all attendees for a Calendar Event.
     *
     * @param IntegrationTester              $i
     */
    public function approveAttendees(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $admin          = $i->grabUser('alaress');
        $title          = 'Test Event, rsvp campus event and approve the rsvp at ' . $timestamp;
        $targetCampuses = [$i->grabCampusByName('Secondary')->getId(), $i->grabCampusByName('Primary')->getId()];
        $targetRoles    = [$i->grabRoleByTitle('Administration')->getId(), $i->grabRoleByTitle('Teacher')->getId()];

        #WHEN
        $createEventRequest = new Payload\Calendar\Event\Create($admin->getId(), $admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('type', 1);
        $createEventRequest->setFormParam('level', Calendar\Event::ACCESS_CAMPUS);
        $createEventRequest->setFormParam('campus[0]', $targetCampuses[0]);
        $createEventRequest->setFormParam('campus[1]', $targetCampuses[1]);
        $createEventRequest->setFormParam('roles[0]', $targetRoles[0]);
        $createEventRequest->setFormParam('roles[1]', $targetRoles[1]);
        $createEventRequest->setFormParam('attendance', 1);
        $i->amGoingTo('Send request to create campus calendar event with attendance');
        $i->sendRequest($createEventRequest);

        $event        = $i->grabCalendarEventByTitle($title);
        $RSVP_Payload = new Payload\Base('POST', '/calendar/full/' . date('d-m-Y', $timestamp), '/service/attendance/setAttendance');
        $RSVP_Payload->setFormParam('event[id]', $event->getId());
        $RSVP_Payload->setFormParam('status', 'add');
        $i->amGoingTo('Send request to RSVP to campus calendar event.');
        $i->sendRequest($RSVP_Payload);

        #WHEN
        $attendanceList       = $i->grabCalendarEventAttendanceList($event->getId());
        $RSVP_Approve_Payload = new Payload\Base('POST', '/calendar/attendance/list/' . $event->getId(), '/calendar/attendance/processModifyAttendanceList');
        $RSVP_Approve_Payload->setFormParam('event[id]', $event->getId());
        $RSVP_Approve_Payload->setFormParam('approve', 'Approve Selected');
        foreach ($attendanceList as $attendee) {
            $RSVP_Approve_Payload->setFormParam('attendanceList[' . $attendee['user_id'] . '][id]', $attendee['id']);
        }
        $i->amGoingTo('Send request to RSVP to campus calendar event.');
        $i->sendRequest($RSVP_Approve_Payload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', 'https://vagrant.dev.schoolbox.cloud/calendar/attendance/list/' . $event->getId());

        $i->sendGET('https://vagrant.dev.schoolbox.cloud/calendar/attendance/list/' . $event->getId());
        $i->seeResponseCodeIs(200);
    }
}
