<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;
use Payload\Base;

class CalendarCest {

    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i)
    {
        $i->loginAdmin();
    }

    /** Create a personal event from the full calendar month view, using a curl post.
     *
     * @param IntegrationTester $i
     * @param Base              $createEventRequest
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createEventFullCalendar(IntegrationTester $i, Base $createEventRequest)
    {
        #GIVEN
        $timestamp=time();
        $admin = $i->getUser('alaress');
        $title='Test Event, personal create from calendar at '.$timestamp;

        #WHEN
        $createEventRequest->setPage('/calendar/ajax/create');
        $createEventRequest->setReferer('/calendar/full');
        $createEventRequest->setFormParam('userId', $admin->getId());
        $createEventRequest->setFormParam('allDay', 1);
        $createEventRequest->setFormParam('date', date('d-m-Y'));
        $createEventRequest->setFormParam('title', $title);
        $createEventRequest->setFormParam('type', Payload\CalendarEvent::getEventTypes()['personal']);

        $response = $i->sendCurlRequest($createEventRequest, true, 'Send request to create calendar event on full calendar page');
        $expectedUrlsReturned=[[IntegrationTester::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Create a personal event from the calendar event modify page.
     *
     * @param IntegrationTester    $i
     * @param Payload\CalendarEvent $createEventRequest
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createPersonalEvent(IntegrationTester $i, Payload\CalendarEvent $createEventRequest)
    {
        #GIVEN
        $timestamp=time();
        $admin = $i->getUser('alaress');
        $title='Test Event, personal create from modify event at '.$timestamp;

        #WHEN
        $createEventRequest->setUserID($admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Payload\CalendarEvent::getEventTypes()['personal']);
        $createEventRequest->setFormParam('campus[]', 1);

        $response = $i->sendCurlRequest($createEventRequest, true, 'Send request to create personal calendar event');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            ['https://vagrant.dev.schoolbox.cloud/calendar/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Create a group event from the calendar event modify page.
     *
     * @param IntegrationTester    $i
     * @param Payload\CalendarEvent $createEventRequest
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createGroupEvent(IntegrationTester $i, Payload\CalendarEvent $createEventRequest)
    {
        #GIVEN
        $timestamp=time();
        $admin = $i->getUser('alaress');
        $title='Test Event, group create from modify event at '.$timestamp;
        $targetGroup = $i->getFirstHomepageIdByTitle('Oak House');

        #WHEN
        $createEventRequest->setUserID($admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Payload\CalendarEvent::getEventTypes()['group']);
        $createEventRequest->setFormParam('group[0]', $targetGroup);
        $createEventRequest->setFormParam('campus[]', 1);

        $response = $i->sendCurlRequest($createEventRequest, true, 'Send request to create group calendar event');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            ['https://vagrant.dev.schoolbox.cloud/calendar/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Create a campus event from the calendar event modify page.
     *
     * @param IntegrationTester    $i
     * @param Payload\CalendarEvent $createEventRequest
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createCampusEvent(IntegrationTester $i, Payload\CalendarEvent $createEventRequest)
    {
        #GIVEN
        $timestamp=time();
        $admin = $i->getUser('alaress');
        $title='Test Event, campus create from modify event at '.$timestamp;
        $targetCampuses=[$i->getCampusByName('Senior')->getId(), $i->getCampusByName('Junior')->getId()];
        $targetRoles=[$i->getRoleByTitle('Administration')->getId(), $i->getRoleByTitle('Teachers')->getId()];

        #WHEN
        $createEventRequest->setUserID($admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Payload\CalendarEvent::getEventTypes()['campus']);
        $createEventRequest->setFormParam('campus[0]', $targetCampuses[0]);
        $createEventRequest->setFormParam('campus[1]', $targetCampuses[1]);
        $createEventRequest->setFormParam('roles[0]', $targetRoles[0]);
        $createEventRequest->setFormParam('roles[1]', $targetRoles[1]);

        $response = $i->sendCurlRequest($createEventRequest, true, 'Send request to create campus calendar event');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            ['https://vagrant.dev.schoolbox.cloud/calendar/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Modify personal event using the calendar event edit page.
     *
     * @param IntegrationTester    $i
     * @param Payload\CalendarEvent $eventPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function modifyPersonalEvent(IntegrationTester $i, Payload\CalendarEvent $eventPayload)
    {
        #GIVEN
        $timestamp=time();
        $admin = $i->getUser('alaress');
        $title='Test Event, personal modify at '.$timestamp;

        $eventPayload->setUserID($admin->getId());
        $eventPayload->setTitle($title);
        $eventPayload->setFormParam('level', Payload\CalendarEvent::getEventTypes()['personal']);
        $eventPayload->setFormParam('campus[]', 1);

        $i->sendCurlRequest($eventPayload, false, 'Send request to create personal calendar event');
        $event = $i->getCalendarEventByTitle($title);

        #WHEN
        $eventPayload->setFormParam('Add', 'Update Event');
        $eventPayload->setFormParam('id', $event->getId());
        $eventPayload->setFormParam('detail', '<p> I have been updated </p>');

        $response = $i->sendCurlRequest($eventPayload, true, 'Send request to modify personal calendar event');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            ['https://vagrant.dev.schoolbox.cloud/calendar/'.date('d-m-Y', $timestamp).'/'.$admin->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** RSVP to a calendar event. Curl post
     *
     * @param IntegrationTester    $i
     * @param Payload\CalendarEvent $createEventRequest
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function rsvpToEvent(IntegrationTester $i, Payload\CalendarEvent $createEventRequest)
    {
        #GIVEN
        $timestamp=time();
        $admin = $i->getUser('alaress');
        $title='Test Event, rsvp campus event at '.$timestamp;
        $targetCampuses=[$i->getCampusByName('Senior')->getId(), $i->getCampusByName('Junior')->getId()];
        $targetRoles=[$i->getRoleByTitle('Administration')->getId(), $i->getRoleByTitle('Teachers')->getId()];


        $createEventRequest->setUserID($admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Payload\CalendarEvent::getEventTypes()['campus']);
        $createEventRequest->setFormParam('campus[0]', $targetCampuses[0]);
        $createEventRequest->setFormParam('campus[1]', $targetCampuses[1]);
        $createEventRequest->setFormParam('roles[0]', $targetRoles[0]);
        $createEventRequest->setFormParam('roles[1]', $targetRoles[1]);
        $createEventRequest->setFormParam('attendance', 1);

        $i->sendCurlRequest($createEventRequest, false, 'Send request to create campus calendar event with attendance');

        #WHEN
        $event = $i->getCalendarEventByTitle($title);
        $RSVP_Payload = new Base();
        $RSVP_Payload->setPage('/service/attendance/setAttendance');
        $RSVP_Payload->setReferer('/calendar/full/'.date('d-m-Y',$timestamp));
        $RSVP_Payload->setFormParam('event[id]', $event->getId());
        $RSVP_Payload->setFormParam('status', 'add');

        $response = $i->sendCurlRequest($RSVP_Payload, true, 'Send request to RSVP to campus calendar event.');
        $expectedUrlsReturned= [[IntegrationTester::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Approve all attendees for a Calendar Event.
     *
     * @param IntegrationTester    $i
     * @param Payload\CalendarEvent $createEventRequest
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function approveAttendees(IntegrationTester $i, Payload\CalendarEvent $createEventRequest)
    {

        #GIVEN
        $timestamp=time();
        $admin = $i->getUser('alaress');
        $title='Test Event, rsvp campus event and approve the rsvp at '.$timestamp;
        $targetCampuses=[$i->getCampusByName('Senior')->getId(), $i->getCampusByName('Junior')->getId()];
        $targetRoles=[$i->getRoleByTitle('Administration')->getId(), $i->getRoleByTitle('Teachers')->getId()];

        #WHEN
        $createEventRequest->setUserID($admin->getId());
        $createEventRequest->setTitle($title);
        $createEventRequest->setFormParam('level', Payload\CalendarEvent::getEventTypes()['campus']);
        $createEventRequest->setFormParam('campus[0]', $targetCampuses[0]);
        $createEventRequest->setFormParam('campus[1]', $targetCampuses[1]);
        $createEventRequest->setFormParam('roles[0]', $targetRoles[0]);
        $createEventRequest->setFormParam('roles[1]', $targetRoles[1]);
        $createEventRequest->setFormParam('attendance', 1);

        $i->sendCurlRequest($createEventRequest, false, 'Send request to create campus calendar event with attendance');

        $event = $i->getCalendarEventByTitle($title);
        $RSVP_Payload = new Base();
        $RSVP_Payload->setPage('/service/attendance/setAttendance');
        $RSVP_Payload->setReferer('/calendar/full/'.date('d-m-Y',$timestamp));
        $RSVP_Payload->setFormParam('event[id]', $event->getId());
        $RSVP_Payload->setFormParam('status', 'add');

        $i->sendCurlRequest($RSVP_Payload, false, 'Send request to RSVP to campus calendar event.');

        #WHEN
        $attendanceList = $i->getCalendarEventAttendanceList($event->getId());
        $RSVP_Approve_Payload = new Base();
        $RSVP_Approve_Payload->setPage('/calendar/attendance/processModifyAttendanceList');
        $RSVP_Approve_Payload->setReferer('/calendar/attendance/list/'.$event->getId());
        $RSVP_Approve_Payload->setFormParam('event[id]', $event->getId());
        $RSVP_Approve_Payload->setFormParam('approve', 'Approve Selected');
        foreach ($attendanceList as $attendee)
        {
            $RSVP_Approve_Payload->setFormParam('attendanceList['.$attendee['user_id'].'][id]', $attendee['id']);
        }


        $response=$i->sendCurlRequest($RSVP_Approve_Payload, true, 'Send request to RSVP to campus calendar event.');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/attendance/list/'.$event->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

    }

}
