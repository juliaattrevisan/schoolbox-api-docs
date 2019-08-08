<?php

namespace SmokeTests;

use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class CalendarCest {
    /**
     * Smoke test calendar post forms include following actions:
     * Create calendar event from full calendar
     * Create a personal calendar event from the modify screen
     * Create a group calendar event from the modify screen
     * Create a campus calendar event from the modify screen
     * Modify a personal calendar event
     * RSVP to calendar event.
     * Approve atendees on event
     *
     * Each of these in the future itteration should be split into sepperate test functions.
     *
     * @param IntegrationTester $i
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function SmokeCalendarPOST(IntegrationTester $i)
    {
        #GIVEN
        $i->loginAdmin(); #we logged in as alaress
        $admin = $i->getUser('alaress');

        #WHEN
        $event_title='Personal event for user '.$admin->getId().'. On full Calendar.';
        $response = $i->createPersonalCalendarEventViaCalendar($event_title, $admin->getId());

        #THEN
        $i->comment('Create calendar event from full calander');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after creating event on full calendar screen.');

        #WHEN
        $event_title='Personal event for user '.$admin->getId().'. On create version of modify event.';
        $response = $i->createPersonalCalendarEvent($event_title, $admin->getId());
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Create calendar event from edit event - Personal event');
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I create a calendar personal event via editinng event.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId(), $redirect_history[0], 'I expect we get redirected to the calendar month our event is on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $groups_effected= [
            $i->getFirstHomepageIdByTitle('Oak House')
        ];
        $event_title='Group event for a group. On create version of modify event.';
        $response = $i->createGroupCalendarEvent($event_title, $admin->getId(), $groups_effected);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Create calendar event from edit event - Group event');
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I create a calendar group event via editinng event.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId(), $redirect_history[0], 'I expect we get redirected to the calendar month our event is on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $roles_effected= [
            $i->getRoleByTitle('Administration')->getId(),
            $i->getRoleByTitle('Teachers')->getId()
        ];
        $campuses_effected= [
            $i->getCampusByName('Senior')->getId(),
            $i->getCampusByName('Junior')->getId()
        ];
        $event_title='Campus event for 2 roles and all the campuses. On create version of modify event.';
        $response = $i->createCampusCalendarEvent($event_title, $admin->getId(), $roles_effected, $campuses_effected);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Create calendar event from edit event - Campus event');
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I create a calendar campus event via editinng event.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId(), $redirect_history[0], 'I expect we get redirected to the calendar month our event is on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $admin = $i->getUser('alaress');
        $event_title='Personal event for user '.$admin->getId().'. Edited Test.';
        $i->createPersonalCalendarEventViaCalendar($event_title, $admin->getId());
        $response = $i->editPersonalCalendarEvent($event_title, $admin->getId());
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Create personal calendar event then edit the personal event.');
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I edit a personal event.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId(), $redirect_history[0], 'I expect we get redirected to the calendar month our event is on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $event_title='Campus event for administrators and teachers '.$admin->getId().'. RCVP Test.';
        $roles_effected= [
            $i->getRoleByTitle('Administration')->getId(),
            $i->getRoleByTitle('Teachers')->getId()
        ];
        $campuses_effected= [
            $i->getCampusByName('Senior')->getId(),
            $i->getCampusByName('Junior')->getId()
        ];
        $i->createCampusCalendarEventWithAttendance($event_title, $admin->getId(), $roles_effected, $campuses_effected);
        $event = $i->getCalendarEventByTitle($event_title);
        $response = $i->RSVPtoEvent($event->getId());

        #THEN
        $i->comment('Create campus calendar event then RCVP to it.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after sending an RSVP to the event.');

        #WHEN
        $users_to_approve=[
            $admin->getId()
        ];
        $response = $i->ApproveAttendeeOnRSVPEvent($event->getId(), $users_to_approve);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Approve attendees on event.');
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after we approve attendance.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/calendar/attendance/list/'.$event->getId(), $redirect_history[0], 'I expect we get redirected to the event we have added attendance to.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');
    }
}