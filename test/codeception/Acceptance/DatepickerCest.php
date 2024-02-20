<?php

/**
 * @copyright   Copyright 2017, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance;

use Exception;
use Tests\Support\AcceptanceTester;
use Codeception\Exception\TestRuntimeException;
use Codeception\Util\Locator;
use Tests\Support\Page\Datepicker as DatepickerPage;

/**
 *
 * creates an event in the calendar, modifies it and ensure the proper date is presented in the date picker
 *
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance DatepickerCest
 *
 */

class DatepickerCest
{
    /**
     * @throws Exception
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->loginAsAdmin();
    }

    /**
     * Scenarios
     */

    /**
     * Event modification form scenario
     *
     * @param AcceptanceTester $i
     * @throws TestRuntimeException if something goes wrong
     */
    public function addEvent(AcceptanceTester $i): void
    {
        $datepickerPage = new DatepickerPage($i);
        $i->amGoingTo('navigate to the calendar page and create an event');
        $i->amOnPage('/calendar/week');
        $i->wait(3);
        $i->clickWithLeftButton('td.fc-day');
        $i->waitForElementVisible('div#createEventPanel', 5);
        $i->fillField('div#createEventPanel input[name=title]', 'Date Picker Test');
        $i->click('input[type=submit][value=Create\ Event]');
        sleep(2);
        $i->amGoingTo('check the event is presented in the calendar');
        $i->canSee('Date Picker Test');
        $i->amGoingTo('modify the event and check the proper date is presented in the date picker');
        $i->amOnPage('/calendar/week');
        $i->click('button.fc-customPrev-button.fc-button.fc-button-primary');
        sleep(2);
        $element = Locator::contains('a.fc-daygrid-event', 'Date Picker Test');
        $i->clickWithLeftButton($element);
        $element = Locator::contains('a.button', 'Modify');
        $i->clickWithLeftButton($element);
        sleep(2);
        $datepickerPage->checkCalendarDate('startDate', '19/06/2017');
    }
}
