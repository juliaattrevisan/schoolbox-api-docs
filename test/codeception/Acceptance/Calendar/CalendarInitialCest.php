<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Calendar;

use Exception;
use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Calendar\Calendar;
use Tests\Support\Page\Calendar\CreateEvent;

/**
 * This test creates a personal event, checks visibility of the event as different users, deletes the event
 * creates personal, group and school level events and check if the dialog presents view, modify and delete buttons
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Calendar/CalendarInitialCest
 */
class CalendarInitialCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in as alaress admin
     * navigate to https://vagrant.dev.schoolbox.cloud/caledar/week
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
        $calendar = new Calendar($i);
        $i->amOnPage($calendar::URL);
        $i->waitForElement($calendar::DAY_PANEL);
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     *
     * creates a personal event and check it is presented properly
     *
     */
    public function createPersonalEvent(AcceptanceTester $i): void
    {
        $calendar = new Calendar($i);
        $evenTitle = 'My Test Event';
        $i->amGoingTo('create a personal event via calendar panel');
        $calendar->clickDayCalendar();
        $i->waitForElementVisible($calendar::EVENT_DIALOG, 5);
        $calendar->typeTitle($evenTitle);
        $calendar->clickCreateEvent();
        sleep(5);
        $i->amGoingTo('check the event is presented in the calendar');
        $i->canSee('My Test Event');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     * @depends createPersonalEvent
     *
     *
     * checks if alaress admin can view their personal event and if Eden Reed cannot view it
     *
     */
    public function viewDifferentForDifferentUsers(AcceptanceTester $i): void
    {
        $calendar = new Calendar($i);
        $evenTitle = 'My Test Event';
        $student = 'Eden Reed';
        $i->amGoingTo('as alaress check if I can view the personal event I have created');
        $calendar->clickPreviousButton();
        sleep(2);
        $i->canSee($evenTitle);
        $i->amGoingTo('as Eden Reed check if I cannot view alaress admin personal event');
        $i->impersonate($student);
        $i->amOnPage($calendar::URL);
        sleep(2);
        $i->cantSee($evenTitle);
        $calendar->clickPreviousButton();
        sleep(2);
        $i->cantSee($evenTitle);
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     * @depends createPersonalEvent
     *
     * deletes the event
     *
     */
    public function deletePersonalEvent(AcceptanceTester $i): void
    {
        $calendar = new Calendar($i);
        $evenTitle = 'My Test Event';
        $i->amGoingTo('delete the personal event');
        $calendar->clickPreviousButton();
        sleep(2);
        $calendar->clickCalendarEvent($evenTitle);
        $calendar->clickDialogButton('Delete');
        sleep(2);
        $calendar->clickConfirmDelete();
        sleep(2);
        $i->cantSee('My Test Event');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     *
     * creates a personal, group and school event and check it presents the proper buttons on the dialog:
     * view, delete and modify buttons
     *
     */
    public function viewEventDialog(AcceptanceTester $i): void
    {
        $calendar = new Calendar($i);
        $createEvent = new CreateEvent($i);
        $user = 'Jack Coles';
        $evenTitleGroup = 'My Group Test Event';
        $evenTitlePersonal = 'My Personal Test Event';
        $evenTitleSchool = 'My School Test Event';
        $i->amGoingTo('as Jack Coles I create a personal event via calendar panel');
        $i->impersonate($user);
        $i->amOnPage($calendar::URL);
        $calendar->clickDayCalendar();
        $i->waitForElementVisible($calendar::EVENT_DIALOG, 5);
        $calendar->typeTitle($evenTitlePersonal);
        $calendar->clickCreateEvent();
        sleep(2);
        $i->amGoingTo('check the event is presented in the calendar');
        $i->canSee($evenTitlePersonal);
        $i->amGoingTo('check the view, modify and delete buttons are presented in the dialog');
        $calendar->clickCalendarEvent($evenTitlePersonal);
        $i->canSee('View details');
        $i->canSee('Modify');
        $i->canSee('Delete');
        $i->amGoingTo('delete the event so it does not impact in the next test');
        $i->updateInDatabase('event', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => $evenTitlePersonal]);
        $i->amGoingTo('as Jack Coles I create a group event');
        $i->amOnPage($createEvent::URL_CREATE_EVENT);
        $i->waitForText('Add Event');
        $createEvent->typeTitle($evenTitleGroup);
        $createEvent->selectLevel($createEvent::LEVEL_TYPE_GROUP);
        $createEvent->selectGroup(55);
        $createEvent->clickSubmitEvent();
        sleep(2);
        $i->amGoingTo('check the event is presented in the calendar');
        $i->amOnPage($calendar::URL);
        $i->canSee($evenTitleGroup);
        $i->amGoingTo('check the view, modify and delete buttons are presented in the dialog');
        $calendar->clickCalendarEvent($evenTitleGroup);
        $i->canSee('View details');
        $i->canSee('Modify');
        $i->canSee('Delete');
        $i->amGoingTo('delete the event so it does not impact in the next test');
        $i->updateInDatabase('event', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => $evenTitleGroup]);
        $i->amGoingTo('set Jack Coles as a Calendar moderator');
        //via ui this is done in the user modify page Permissions section
        $i->logout();
        $i->loginAsAdmin();
        $i->haveInDatabase('userAccess', ['uid' => 22,'service' => 2,'sid' => 2,'type' => 3,'favourite' => 0,'created_at' => "2020-01-15 11:22:34"]);
        $i->amGoingTo('as Jack Coles I create a school event');
        $i->impersonate($user);
        $i->amOnPage($createEvent::URL_CREATE_EVENT);
        $i->waitForText('Add Event');
        $createEvent->typeTitle($evenTitleSchool);
        $createEvent->selectLevel($createEvent::LEVEL_TYPE_SCHOOL);
        $createEvent->selectRole(2);
        $createEvent->selectCampus(2);
        $createEvent->clickSubmitEvent();
        sleep(2);
        $i->amGoingTo('check the event is presented in the calendar');
        $i->amOnPage($calendar::URL);
        $i->canSee($evenTitleSchool);
        $i->amGoingTo('check the view, modify and delete buttons are presented in the dialog');
        $calendar->clickCalendarEvent($evenTitleSchool);
        $i->canSee('View details');
        $i->canSee('Modify');
        $i->canSee('Delete');
        $i->amGoingTo('delete the event so it does not impact in the next test');
        $i->updateInDatabase('event', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => $evenTitleSchool]);
    }
}
