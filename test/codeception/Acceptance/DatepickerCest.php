<?php

namespace acceptance;

use Tests\Support\AcceptanceTester;
use Codeception\Exception\TestRuntimeException;
use Codeception\Util\Locator;
use Tests\Support\Page\Datepicker as DatepickerPage;

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
    public function addEvent(AcceptanceTester $i): void
    {
        $datepickerPage = new DatepickerPage($i);
        $i->amOnPage('/calendar/week');
        $i->wait(3);
        $i->clickWithLeftButton('td.fc-day');
        $i->waitForElementVisible('div#createEventPanel', 5);
        $i->fillField('div#createEventPanel input[name=title]', 'Date Picker Test');
        $i->click('input[type=submit][value=Create\ Event]');
        sleep(2);
        $i->canSee('Date Picker Test');
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
