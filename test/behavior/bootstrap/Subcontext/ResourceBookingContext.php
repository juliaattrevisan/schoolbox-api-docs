<?php

/**
 * Contains test steps for the resource booking system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_ResourceBookingContext extends BaseContext
{

    /**
     * Helper method for finding a resource.
     *
     * @param  string $name        The name of the resource to find.
     * @return Element\NodeElement The base element for the resource.
     * @throws Exception           if the resource list is not on the page, or
     *                             the named resource is not found.
     */
    protected function findResource($name)
    {
        $list = $this->getSession()->getPage()->find('css', '.resource-list');
        if (!($list instanceof Element\NodeElement)) {
            throw new Exception('Resource list not found on page');
        }
        foreach ($list->findAll('css', '.resource') as $resource) {
            /** @var Element\NodeElement $resource */
            $nameElement = $resource->find('css', '.resource-name a');
            if ($nameElement instanceof Element\NodeElement && $nameElement->getText() === $name) {
                return $resource;
            }
        }
        throw new Exception(sprintf('Resource with name "%s" not found', $name));
    }

    /**
     * Views details for a resource, provided the user is on the resource
     * booking page.
     *
     * @When /^(?:|I )view details for resource "(?P<name>[^"]*)"$/
     *
     * @param string $name
     */
    public function viewResource($name)
    {
        $resource = $this->findResource($name);
        $resource->find('css', '.resource-read-link')->click();
    }

    /**
     * Edits a resource, provided the user is on the resource booking page.
     *
     * @When /^(?:|I )edit resource "(?P<name>[^"]*)"$/
     *
     * @param string $name
     */
    public function editResource($name)
    {
        $resource = $this->findResource($name);
        $resource->find('css', '.resource-edit-link')->click();
    }

    /**
     * Starts creating a booking for a resource, by clicking on a time slot.
     * Currently switches to calendar's month view to do this, because that's
     * the only view that stores a reference to the date.
     *
     * @When /^(?:|I )start booking resource$/
     * @When /^(?:|I )start booking resource for "(?P<timeString>[^"]*)"$/
     *
     * @param string $timeString A valid date time format string
     */
    public function startBookingResource($timeString = 'today')
    {
        $this
            ->getMainContext()->getSubcontext('calendar')
            ->clickDate('#calendar', $timeString);
        $this->saveScreenshot();
        $this
            ->getSession()->getPage()
            ->find('css', "#createEvent .bookingActions input.btn[value=\"Continue\"]")
            ->click();
    }

    /**
     * Asserts creating a booking for a resource, by clicking on a time slot.
     * Currently switches to calendar's month view to do this, because that's
     * the only view that stores a reference to the date.
     *
     * @When /^(?:|I )should not be able to book resource$/
     * @When /^(?:|I )should not be able to book resource for "(?P<timeString>[^"]*)"$/
     *
     * @param string $timeString A valid date time format string
     * @throws Exception if user can book resource
     */
    public function assertCannotBookResource($timeString = 'today')
    {
        $this
            ->getMainContext()->getSubcontext('calendar')
            ->clickDate('#calendar', $timeString);
        $button = $this
            ->getSession()->getPage()
            ->find('css', "#createEvent .bookingActions input.btn[value=\"Continue\"]");
        if ($button instanceof Element\NodeElement) {
            throw new Exception('User was able to book resource');
        }
    }

    /**
     * Is there a booking with the given status starting at this time? If so
     * return it.
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $status     The status (approved, pending or rejected),
     *                            if any. If not supplied, doesn't filter on
     *                            status.
     * @return Element\NodeElement
     * @throws Exception if no booking was found
     */
    public function getBooking($timeString, $status = null)
    {
        /** @var Subcontext_CalendarContext $calendarContext */
        $calendarContext = $this->getMainContext()->getSubcontext('calendar');
        $calendarContext->clickDate('#calendar', $timeString);
        foreach ($calendarContext->getEventsStartingAtTime('#calendar', $timeString) as $eventElement) {
            /** @var Element\NodeElement $eventElement */
            if (!isset($status) || $eventElement->hasClass($status)) {
                return $eventElement;
            }
        }
        throw new Exception("No " . ($status ? "$status " : "") . "booking found in calendar for time \"$timeString\"");
    }

    /**
     * Is there a booking with the given status starting at this time? If so
     * return it.
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $action     The status action (approve, reject, cancel).
     * @return Element\NodeElement
     * @throws Exception if no booking was found
     * @throws Exception if button wasn't found
     */
    public function getActionButton($timeString, $action = null)
    {
        $booking = $this->getBooking($timeString);
        $booking->click();
        $button = $this->getSession()->getPage()->find('css', "#calendar-bubble .bookingActions a.btn.booking-$action");
        if ($button instanceof Element\NodeElement) {
            return $button;
        }
        throw new Exception("Button for $action booking was not found in calendar for event at time \"$timeString\"");
    }

    /**
     * Asserts that there's a booking with the given status starting at this time.
     *
     * @Then /^(?:|I )should see (?P<status>approved|pending|rejected) booking at "(?P<timeString>[^"]*)"$/
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $status     The status (approved, pending or rejected)
     * @throws Exception if no booking was found
     */
    public function assertBookingExists($timeString, $status)
    {
        $this->getBooking($timeString, $status);
    }

    /**
     * Asserts that there's no booking with the given status starting at this time.
     *
     * This expects that the calendar actually exists.
     *
     * @Then /^(?:|I )should not see (?P<status>approved|pending|rejected) booking at "(?P<timeString>[^"]*)"$/
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $status     The status (approved, pending or rejected)
     * @throws Exception if a booking was found
     */
    public function assertBookingNotExists($timeString, $status)
    {
        try {
            $this->getBooking($timeString, $status);
        } catch (Exception $e) {
            return;
        }
        throw new Exception("A $status booking was found in calendar for time \"$timeString\"");
    }

    /**
     * Asserts that there's no booking with the given status starting at this time.
     *
     * This expects that the calendar actually exists.
     *
     * @Then /^(?:|I )(?P<action>approve|reject|cancel) booking at "(?P<timeString>[^"]*)"$/
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $action     The status action (approve, reject, cancel)
     * @throws Exception if no booking was found
     * @throws Exception if button was not found
     */
    public function changeBookingStatus($timeString, $action)
    {
        $button = $this->getActionButton($timeString, $action);
        $button->click();
    }


    /**
     * Asserts that the given action is not possible for the given event.
     *
     * @Then /^(?:|I )should not be able to (?P<action>approve|reject|cancel) booking at "(?P<timeString>[^"]*)"$/
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $action     The status action (approve, reject, cancel)
     * @throws Exception if button was found
     */
    public function assertCannotChangeBookingStatus($timeString, $action)
    {
        try {
            $this->getActionButton($timeString, $action);
        } catch (Exception $e) {
            return;
        }
        throw new Exception("A $action button was found for event at time \"$timeString\"");
    }
}
