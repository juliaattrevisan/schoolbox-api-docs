<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Calendar;

use Exception;
use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;

/**
 * This test creates a personal event, checks visibility of the event as different users, deletes the event
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
        $i->amOnPage('/calendar/week');
        sleep(5);
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws \Exception
     *
     * creates a personal event and check it is presented properly
     *
     */
    public function createPersonalEvent(AcceptanceTester $i): void
    {
        $i->amGoingTo('create a personal event via calendar panel');
        $i->clickWithLeftButton('td.fc-day');
        $i->waitForElementVisible('div#createEventPanel', 5);
        $i->fillField('div#createEventPanel input[name=title]', 'My Test Event');
        $i->click('input[type=submit][value=Create\ Event]');
        sleep(5);
        $i->amGoingTo('check the event is presented in the calendar');
        $i->canSee('My Test Event');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     *
     * checks if alaress admin can view their personal event and if Eden Reed cannot view it
     *
     */
    public function viewDifferentForDifferentUsers(AcceptanceTester $i): void
    {
        $i->amGoingTo('as alaress check if I can view the event I have created');
        $i->click('button.fc-customPrev-button.fc-button.fc-button-primary');
        sleep(2);
        $i->canSee('My Test Event');
        $i->amGoingTo('as Eden Reed check if I cannot view alaress admin personal event');
        $i->impersonate('Eden Reed');
        $i->amOnPage('/calendar/week');
        sleep(2);
        $i->cantSee('My Test Event');
        $i->click('button.fc-customPrev-button.fc-button.fc-button-primary');
        sleep(2);
        $i->cantSee('My Test Event');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     *
     * deletes the event
     *
     */
    public function deletePersonalEvent(AcceptanceTester $i): void
    {
        $i->amGoingTo('delete the calendar event');
        $i->click('button.fc-customPrev-button.fc-button.fc-button-primary');
        sleep(2);
        $element = Locator::contains('a.fc-daygrid-event', 'My Test Event');
        $i->clickWithLeftButton($element);
        $element = Locator::contains('a.button', 'Delete');
        $i->clickWithLeftButton($element);
        sleep(2);
        $i->clickWithLeftButton(Locator::lastElement('a.button.submit'));
        sleep(2);
        $i->cantSee('My Test Event');
    }
}
