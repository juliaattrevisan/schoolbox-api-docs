<?php

/**
 * Contains test steps that relate to datepickers.
 *
 * All datepickers are assumed to contain dates in format "d/m/Y".
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_DatepickerContext extends BaseContext
{

    /**
     * Helper function for converting a date into a input field.
     *
     * @param  string $value
     * @return string
     */
    protected function convertValueToDatepicker($value)
    {
        $datetime = new DateTime($value);
        return $datetime->format('d/m/Y');
    }

    /**
     * Asserts that the given datepicker contains a given date.
     *
     * @Then /^the datepicker "(?P<field>[^"]*)" should contain "(?P<value>[^"]*)"$/
     *
     * @param string $field The locator for the datepicker field.
     * @param string $value A datetime in any recognised date format.
     */
    public function assertDatepickerValue($field, $value)
    {
        $this->getMinkContext()->assertFieldContains($field, $this->convertValueToDatepicker($value));
    }

    /**
     * Puts a date into a datepicker.
     *
     * @When /^(?:|I )fill in datepicker "(?P<field>[^"]*)" with "(?P<value>(?:[^"]|\\")*)"$/
     * @When /^(?:|I )fill in datepicker "(?P<field>[^"]*)" with:$/
     * @When /^(?:|I )fill in "(?P<value>[^"]*)" for datepicker "(?P<field>[^"]*)"$/
     *
     * @param string $field The locator for the datepicker field.
     * @param string $value A datetime in any recognised date format.
     */
    public function fillDatepickerField($field, $value)
    {
        $this->getMinkContext()->fillField($field, $this->convertValueToDatepicker($value));
    }
}
