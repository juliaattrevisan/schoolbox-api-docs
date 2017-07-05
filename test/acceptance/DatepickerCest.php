<?php

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
     * @param AcceptanceTester $i
     */
    public function addEvent(AcceptanceTester $i) {
        $datepickerPage = new DatepickerPage($i);
        $i->amOnPage('/calendar/modify.php');
        $i->wait(3);
        $datepickerPage->checkCalendarDate('startDate', '01/02/2017');
    }

}