<?php
/**
 * Contains test steps for the resource booking system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Behat\Mink\Element;

class Subcontext_ResourceBookingContext extends BaseContext {

    /**
     * Helper method for finding a resource.
     *
     * @param  string $name        The name of the resource to find.
     * @return Element\NodeElement The base element for the resource.
     * @throws Exception           if the resource list is not on the page, or
     *                             the named resource is not found.
     */
    protected function findResource($name) {
        $list = $this->getSession()->getPage()->find('css', '.resource-list');
        if (!($list instanceof Element\NodeElement)) {
            throw new Exception('Resource list not found on page');
        }
        foreach ($list->findAll('css', '.resource') as $resource) {
            /** @var Element\NodeElement $resource */
            $nameElement = $resource->find('css', '.resource-name');
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
    public function viewResource($name) {
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
    public function editResource($name) {
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
    public function startBookingResource($timeString = 'today') {
        $this
            ->getMainContext()->getSubcontext('calendar')
            ->clickDate('#calendar', $timeString);
        $this
            ->getSession()->getPage()
            ->find('css', "#createEvent .actions input.btn[value=\"Continue\"]")
            ->click();
    }

    /**
     * Is there a booking with the given status starting at this time?
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $status     The status (approved, pending or rejected)
     * @return bool
     */
    public function bookingExists($timeString, $status) {
        /** @var Subcontext_CalendarContext $calendarContext */
        $calendarContext = $this->getMainContext()->getSubcontext('calendar');
        $calendarContext->clickDate('#calendar', $timeString);
        foreach ($calendarContext->getEventsStartingAtTime('#calendar', $timeString) as $eventElement) {
            /** @var Element\NodeElement $eventElement */
            if ($eventElement->hasClass($status)) {
                return true;
            }
        }
        return false;
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
    public function assertBookingExists($timeString, $status) {
        if (!$this->bookingExists($timeString, $status)) {
            throw new Exception("No $status booking found in calendar for time \"$timeString\"");
        }
    }

    /**
     * Asserts that there's no booking with the given status starting at this time.
     *
     * @Then /^(?:|I )should not see (?P<status>approved|pending|rejected) booking at "(?P<timeString>[^"]*)"$/
     *
     * @param  string $timeString When should the booking be for?
     * @param  string $status     The status (approved, pending or rejected)
     * @throws Exception if a booking was found
     */
    public function assertBookingNotExists($timeString, $status) {
        if ($this->bookingExists($timeString, $status)) {
            throw new Exception("A $status booking was found in calendar for time \"$timeString\"");
        }
    }

}
