<?php

use Codeception\Exception\TestRuntimeException;
use Codeception\Util\Locator;
use Page\Datepicker as DatepickerPage;

class DatepickerCest
{

    public function _before(AcceptanceTester $i)
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
    public function addEvent(AcceptanceTester $i)
    {
        $datepickerPage = new DatepickerPage($i);
        $i->amOnPage('/calendar/full');
        $i->wait(3);
        $i->clickWithLeftButton('td.fc-day');
        $i->waitForElementVisible('div#createEventPanel', 5);
        $i->fillField('div#createEventPanel input[name=title]', 'Date Picker Test');
        $i->click('input[type=submit][value=Create\ Event]');
        $i->canSee('Date Picker Test');
        $i->amOnPage('/calendar/full');
        sleep(2);
        $element = Locator::contains('a.fc-day-grid-event', 'Date Picker Test');
        $i->clickWithLeftButton($element);
        $element = Locator::contains('a.button', 'Modify');
        $i->clickWithLeftButton($element);
        sleep(2);
        $datepickerPage->checkCalendarDate('startDate', '01/02/2017');
    }

}
