<?php

namespace Calendar;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;

class CalendarInitialCest
{
    public function createPersonalEvent(AcceptanceTester $i): void
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/calendar/week');
        sleep(5);
        $i->clickWithLeftButton('td.fc-day');
        $i->waitForElementVisible('div#createEventPanel', 5);
        $i->fillField('div#createEventPanel input[name=title]', 'My Test Event');
        $i->click('input[type=submit][value=Create\ Event]');
        sleep(5);
        $i->canSee('My Test Event');
    }

    public function viewDifferentForDifferentUsers(AcceptanceTester $i): void
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/calendar/week');
        $i->click('button.fc-customPrev-button.fc-button.fc-button-primary');
        sleep(2);
        $i->canSee('My Test Event');
        $i->impersonate('Eden Reed');
        $i->amOnPage('/calendar/week');
        sleep(2);
        $i->cantSee('My Test Event');
        $i->click('button.fc-customPrev-button.fc-button.fc-button-primary');
        sleep(2);
        $i->cantSee('My Test Event');
    }

    public function deletePersonalEvent(AcceptanceTester $i): void
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/calendar/week');
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
