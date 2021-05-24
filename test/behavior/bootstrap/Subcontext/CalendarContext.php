<?php

/**
 * Contains test steps that relate to the fullCalendar jQuery plugin.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_CalendarContext extends BaseContext
{

    /**
     * Visits a date on the given calendar.
     *
     * @When /^(?:|I )go to date "(?P<timeString>[^"]*)" on calendar "(?P<locator>[^"]*)"$/
     *
     * @param string $locator
     * @param string $timeString
     */
    public function goToDate($locator, $timeString)
    {
        $date   = new DateTime($timeString);
        $format = $date->format('Y-m-d');
        // Find this day on the full calendar
        $this->getSession()->executeScript(
            sprintf(
                'jQuery(%s).fullCalendar("changeView", "month").fullCalendar("gotoDate", new Date(%s))',
                json_encode($locator),
                json_encode($format)
            )
        );
        $this->getCoreContext()->waitForAjax();
    }

    /**
     * Gets the calendar element out of the page.
     *
     * @param $locator
     *
     * @return Element\NodeElement
     * @throws Exception
     */
    public function getCalendar($locator)
    {
        $calendar = $this->getSession()->getPage()->find('css', $locator);
        if (!($calendar instanceof Element\NodeElement)) {
            throw new Exception("Calendar \"$locator\" not found");
        }
        return $calendar;
    }

    /**
     * Retrieves a date element from the calendar.
     *
     * @param $locator
     * @param $timeString
     *
     * @return Element\NodeElement
     * @throws Exception if either calendar or date cannot be found.
     */
    public function getDateElement($locator, $timeString)
    {
        $this->goToDate($locator, $timeString);
        $date   = new DateTime($timeString);
        $format = $date->format('Y-m-d');
        $calendar = $this->getCalendar($locator);
        $dateElement = $calendar->find('css', ".fc-day[data-date=\"$format\"]");
        if (!($dateElement instanceof Element\NodeElement)) {
            throw new Exception("Element for date \"$timeString\" on \"$locator\" not found");
        }
        return $dateElement;
    }

    /**
     * Clicks a day on the given calendar, possibly bringing up a button.
     *
     * @When /^(?:|I )click on date "(?P<timeString>[^"]*)" on calendar "(?P<locator>[^"]*)"$/
     *
     * @param string $locator
     * @param string $timeString
     */
    public function clickDate($locator, $timeString)
    {
        $this->getDateElement($locator, $timeString)->click();
    }

    /**
     * Retrieves events starting at a given time from the calendar.
     *
     * @param $locator
     * @param $timeString
     *
     * @return Element\NodeElement
     * @throws Exception if either calendar or date cannot be found.
     */
    public function getEventsStartingAtTime($locator, $timeString)
    {
        $time      = new DateTime($timeString);
        $timestamp = $time->getTimestamp();
        $calendar  = $this->getCalendar($locator);
        return array_filter(
            $calendar->findAll('css', '.fc-event'),
            function ($event) use ($timestamp) {
                /** @var Element\NodeElement $event */
                $startTime = new DateTime($event->getAttribute('data-start'));
                return $startTime->getTimestamp() === $timestamp;
            }
        );
    }
}
