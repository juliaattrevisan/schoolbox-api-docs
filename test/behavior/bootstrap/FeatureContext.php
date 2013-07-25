<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;

//require_once 'PHPUnit/Autoload.php';
//require_once 'PHPUnit/Framework/Assert/Functions.php';

/**
 * Features context.
 */
class FeatureContext extends MinkContext {

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
     * Logs in user.
     *
     * @Given /^I am logged in as "([^"]*)" with password "([^"]*)"$/
     * @param string $username
     * @param string $password
     * @throws Exception if login failed
     */
    public function login($username, $password) {
        $page = $this->getSession()->getPage();
        $page->findById('username')->setValue($username);
        $page->findById('password')->setValue($password);
        $page->find('css', 'input.login')->press();
        if ($this->getSession()->evaluateScript('$j(\'a[href="/logout.php"]\').length') != 1) {
            throw new Exception('Could not login');
        }
    }

    /**
     * Creates a new template on either the homepage, eportfolio or dashboard
     * pages.
     *
     * @Given /^I create a template "([^"]*)"$/
     * @param string $name The name of the template.
     */
    public function createTemplate($name) {
        $form = $this->getSession()->getPage()->findById('template-action');
        $form->findField('name')->setValue($name);
        $form->findButton('new')->press();
        //
        // Once loaded - select this button and wait for it to show.
        // A new item won't have anything there.
        $form->findField('template')->selectOption($name);
        $this->waitForAjax();
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
     * @Then /^I see component "([^"]*)" on "([^"]*)" in position "([^"]*)"$/
     */
    public function iSeeComponentOnInPosition($name, $section, $position) {
        throw new PendingException();
    }

    /**
     * Waits for all AJAX requests to return before continuing.
     *
     * @param int $duration Milliseconds to wait for AJAX requests to finish.
     *
     * @throws Exception if AJAX requests didn't finish in $duration
     */
    protected function waitForAjax($duration = 5000) {
        $ajax_finished_condition = '(0 === jQuery.active)';
        $this->getSession()->wait($duration, $ajax_finished_condition);
        if (!$this->getSession()->evaluateScript($ajax_finished_condition)) {
            throw new Exception('Ajax wait timed out');
        }
    }

}
