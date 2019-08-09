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
        $expectedUrlsReturned=[['-'=>'200']];

        #THEN
        $i->comment('Create calendar event from full calander');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $event_title='Personal event for user '.$admin->getId().'. On create version of modify event.';
        $response = $i->createPersonalCalendarEvent($event_title, $admin->getId());
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId()=>'302'],
            ['-'=>'200']
        ];

        #THEN
        $i->comment('Create calendar event from edit event - Personal event');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $groups_effected= [
            $i->getFirstHomepageIdByTitle('Oak House')
        ];
        $event_title='Group event for a group. On create version of modify event.';
        $response = $i->createGroupCalendarEvent($event_title, $admin->getId(), $groups_effected);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId()=>'302'],
            ['-'=>'200']
        ];

        #THEN
        $i->comment('Create calendar event from edit event - Group event');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

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
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId()=>'302'],
            ['-'=>'200']
        ];

        #THEN
        $i->comment('Create calendar event from edit event - Campus event');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $admin = $i->getUser('alaress');
        $event_title='Personal event for user '.$admin->getId().'. Edited Test.';
        $i->createPersonalCalendarEventViaCalendar($event_title, $admin->getId());
        $response = $i->editPersonalCalendarEvent($event_title, $admin->getId());
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/full/19-07-2019/'.$admin->getId()=>'302'],
            ['-'=>'200']
        ];

        #THEN
        $i->comment('Create personal calendar event then edit the personal event.');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

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
        $expectedUrlsReturned= [['-'=>'200']];

        #THEN
        $i->comment('Create campus calendar event then RCVP to it.');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $users_to_approve=[
            $admin->getId()
        ];
        $response = $i->ApproveAttendeeOnRSVPEvent($event->getId(), $users_to_approve);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/calendar/attendance/list/'.$event->getId()=>'302'],
            ['-'=>'200']
        ];

        #THEN
        $i->comment('Approve attendees on event.');
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}