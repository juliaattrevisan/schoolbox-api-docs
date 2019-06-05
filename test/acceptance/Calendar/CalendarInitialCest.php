<?php

use Codeception\Util\Locator;

class CalendarInitialCest
{

    public function createPersonalEvent(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/calendar/full');
        sleep(5);
        $i->clickWithLeftButton('td.fc-day');
        $i->waitForElementVisible('div#calendar-create', 5);
        $i->fillField('div#calendar-create input[name=title]', 'My Test Event');
        $i->click('input[type=submit][value=Create\ Event]');
        sleep(5);
        $i->canSee('My Test Event');
    }

    public function viewDifferentForDifferentUsers(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/calendar/full');
        sleep(5);
        $i->canSee('My Test Event');
        $i->impersonate('David Rhodes');
        $i->amOnPage('/calendar/full');
        sleep(5);
        $i->cantSee('My Test Event');
    }

    public function deletePersonalEvent(AcceptanceTester $i)
    {
        $i->amOnPage('/login/');
        $i->login("alaress", "alaress");
        $i->amOnPage('/calendar/full');
        sleep(5);
        $element = Locator::contains('a.fc-day-grid-event', 'My Test Event');
        $i->clickWithLeftButton($element);
        $i->waitForElementVisible('div#calendar-bubble', 5);
        $i->click('div#calendar-bubble a.button.icon-delete');
        $i->waitForElementVisible('div.reveal-modal.open', 5);
        $i->click('div.reveal-modal.open a.button.submit');
        sleep(5);
        $i->cantSee('My Test Event');
    }
}