<?php
/**
 * Contains test steps that relate to the fullCalendar jQuery plugin.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Behat\Mink\Element;

class Subcontext_CalendarContext extends BaseContext {

    /**
     * Visits a date on the given calendar.
     *
     * @When /^(?:|I )go to date "(?P<timeString>[^"]*)" on calendar "(?P<locator>[^"]*)"$/
     *
     * @param string $locator
     * @param string $timeString
     */
    public function goToDate($locator, $timeString) {
        $date   = new DateTime($timeString);
        $format = $date->format('Y-m-d');
        // Find this day on the full calendar
        $this->getSession()->executeScript(
            sprintf(
                'jQuery(%s).fullCalendar("changeView", "month").fullCalendar("gotoDate", %s)',
                json_encode($locator),
                json_encode($format)
            )
        );
        $this->getCoreContext()->waitForAjax();
    }

    /**
     * Clicks a day on the given calendar, possibly bringing up a button.
     *
     * @When /^(?:|I )click on date "(?P<timeString>[^"]*)" on calendar "(?P<locator>[^"]*)"$/
     *
     * @param string $locator
     * @param string $timeString
     */
    public function clickDate($locator, $timeString) {
        $this->goToDate($locator, $timeString);
        $date   = new DateTime($timeString);
        $format = $date->format('Y-m-d');
        $this
            ->getSession()->getPage()
            ->find('css', "$locator .fc-day[data-date=\"$format\"]")
            ->click();
    }

}