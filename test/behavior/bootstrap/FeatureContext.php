<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Features context.
 */
class FeatureContext extends BehatContext {
    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters) {
        // Initialize your context here
    }
    /**
     * @Given /^I am logged in as "([^"]*)" with password "([^"]*)"$/
     */
    public function iAmLoggedInAsWithPassword($username, $password) {
        throw new PendingException();
    }

    /**
     * @When /^I go to page "([^"]*)"$/
     */
    public function iGoToPage($url) {
        throw new PendingException();
    }

    /**
     * @Given /^I create a template "([^"]*)"$/
     */
    public function iCreateATemplate($name) {
        throw new PendingException();
    }

    /**
     * @Given /^I add "([^"]*)" to "([^"]*)" at position "([^"]*)"$/
     */
    public function iAddToAtPosition($component, $section, $position) {
        throw new PendingException();
    }

    /**
     * @Given /^I rename component on "([^"]*)" at position "([^"]*)" to "([^"]*)"$/
     */
    public function iRenameComponentOnAtPositionTo($section, $position, $new_name) {
        throw new PendingException();
    }

    /**
     * @Given /^I repurpose component on "([^"]*)" at position "([^"]*)" to "([^"]*)"$/
     */
    public function iRepurposeComponentOnAtPositionTo($section, $position, $new_purpose) {
        throw new PendingException();
    }

    /**
     * @Then /^I click link "([^"]*)"$/
     */
    public function iClickLink($selector) {
        throw new PendingException();
    }

    /**
     * @Given /^I type "([^"]*)" in "([^"]*)"$/
     */
    public function iTypeIn($selector, $value) {
        throw new PendingException();
    }

    /**
     * @Given /^I select "([^"]*)" in "([^"]*)"$/
     */
    public function iSelectIn($selector, $label) {
        throw new PendingException();
    }

    /**
     * @Given /^I press "([^"]*)"$/
     */
    public function iPress($selector) {
        throw new PendingException();
    }

    /**
     * @Then /^I see component "([^"]*)" on "([^"]*)" in position "([^"]*)"$/
     */
    public function iSeeComponentOnInPosition($name, $section, $position) {
        throw new PendingException();
    }

}
