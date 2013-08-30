<?php
/**
 * Contains test steps that are intended to be generic across many tests.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Behat\Mink\Element;

class Subcontext_CoreContext extends BaseContext {

    /**
     * Logs in user.
     *
     * @Given /^(?:|I am) logged in as "(?P<username>[^"]*)" with password "(?P<password>[^"]*)"$/
     * @Given /^(?:|I )log in as "(?P<username>[^"]*)" with password "(?P<password>[^"]*)"$/
     *
     * @param string $username
     * @param string $password
     * @throws Exception if login failed
     */
    public function login($username, $password) {
        $page = $this->getSession()->getPage();
        $page->findById('username')->setValue($username);
        $page->findById('password')->setValue($password);
        $page->find('css', 'input.login')->press();
        $this->spin(
            function($context) {
                /** @param FeatureContext $context */
                return $context->getSession()->getPage()->findLink('Logout') instanceof Element\NodeElement;
            },
            10
        );
    }

    /**
     * Visits a homepage, given a folder path from the resource browser.
     *
     * @Then /^I go to homepage "(?P<homepage_path>[^"]*)"$/
     *
     * @param string $homepage_path
     * @throws Exception if the folder was not found.
     */
    public function goToHomepage($homepage_path) {
        $this->getMinkContext()->visit('/resources/');
        $dirname_list = array_filter(array_map('trim', explode('/', $homepage_path)), 'strlen');

        $folder_browser    = $this->getSession()->getPage()->find('css', '#category-window');
        $current_folder_id = 0;

        $count = count($dirname_list);
        foreach ($dirname_list as $i => $dirname) {
            // Click the expand button, if there is one. If this is the last
            // item, click "visit homepage" instead.
            /** @var Element\NodeElement[] $child_folder_list */
            $child_folder_list = $folder_browser->findAll('css', '.child-of-node-'.$current_folder_id);
            $is_dirname_found  = false;
            foreach ($child_folder_list as $child_folder) {
                $label       = $child_folder->find('css', '.label');
                $folder_name = $label->getText();

                if ($dirname === $folder_name) {
                    $is_dirname_found = true;
                    list(, $current_folder_id) = explode('-', $child_folder->getAttribute('id'));
                    if ($i < ($count-1)) {
                        // Not last folder: drill down to next in tree
                        $child_folder->find('css', '.elbow')->click();
                        $this->getCoreContext()->waitForAjax();
                    }
                    else {
                        // Last folder: open node, click "View Homepage"
                        $label->click();
                        $this->getCoreContext()->waitForAjax();
                        $this->getSession()->getPage()->find('css', '#detail-window a.rhomepage')->click();
                    }
                    break;
                }
            }
            if (!$is_dirname_found) {
                throw new Exception(
                    sprintf(
                        'Could not find folder "%s" in path "%s"',
                        $dirname, implode('/', array_slice($dirname_list, 0, $i))
                    )
                );
            }
        }
    }

    /**
     * Sets a config setting in the admin section.
     *
     * @Then /^(?:|I )set (?:config|setting|configuration) "(?P<type>[^:]*):(?P<name>[^"]*)" to "(?P<value>[^"]*)"$/
     *
     * @param string $type
     * @param string $name
     * @param string $value
     * @throws Exception if the form was not found.
     *
     */
    public function setConfigurationSetting($type, $name, $value) {
        $this->getMinkContext()->visit('/adminv2/setting/'.$type);
        $form = $this->getSession()->getPage()->find('css', 'form#settings');

        // Name could be either a label text or field name, try field name first.
        $field = $form->findField($name);
        if (!$field) {
            /** @var Element\NodeElement[] $labels */
            $labels = $form->findAll('css', 'label');
            foreach ($labels as $label) {
                // Anything after beginning of a tag isn't part of field name
                $label_name = $this->getBeginningText($label);
                if ($label_name === $name) {
                    $field = $form->findField($label->getAttribute('for'));
                    break;
                }
            }
        }
        if (!$field) {
            throw new Exception(sprintf('Could not find setting "%s:%s"', $type, $name));
        }
        $field->setValue($value);
        $form->findButton('Save')->press();
    }

    /**
     * Waits for an element with specified CSS to exist on this page.
     *
     * Required because Selenium doesn't wait for the page to load by default
     * when clicking a link. This isn't a bad thing to have anyway, what with
     * AJAX requests and all that.
     *
     * @see $this->assertElementOnPage()
     *
     * @Then /^(?:|I )wait for an? "(?P<element>[^"]*)" element$/
     * @Then /^(?:|I )wait (?P<duration>\d+)ms for an? "(?P<element>[^"]*)" element$/
     */
    public function waitForElementOnPage($element, $duration = 5000) {
        $this->spin(
            function($context) use($element) {
                /** @param FeatureContext $context */
                $context->getMinkContext()->assertElementOnPage($element);
                return true;
            },
            ($duration/1000)
        );
    }

    /**
     * Waits for an element with specified CSS to exist on this page.
     *
     * Required because Selenium doesn't wait for the page to load by default
     * when clicking a link. This isn't a bad thing to have anyway, what with
     * AJAX requests and all that.
     *
     * @see $this->fillField()
     *
     * @Then /^(?:|I )wait for an? "(?P<field>[^"]*)" field$/
     * @Then /^(?:|I )wait (?P<duration>\d+)ms for an? "(?P<field>[^"]*)" field$/
     */
    public function waitForFieldOnPage($field, $duration = 5000) {
        $this->spin(
            function($context) use($field) {
                /** @param FeatureContext $context */
                $element = $context->getSession()->getPage()->findField($field);
                return $element instanceof Element\NodeElement;
            },
            ($duration/1000)
        );
    }

    /**
     * Waits for all AJAX requests to return before continuing.
     *
     * @Then /^I wait for AJAX to finish$/
     * @Then /^I wait (?P<duration>\d+)ms for AJAX to finish$/
     *
     * @param int $duration Milliseconds to wait for AJAX requests to finish.
     *
     * @throws Exception if AJAX requests didn't finish in $duration
     */
    public function waitForAjax($duration = 5000) {
        //
        // wat. Sahi fails with a return in the script to evaluate,
        // Selenium fails without one. FAIL
        $script = '(0 === jQuery.active)';
        if ($this->getSession()->getDriver() instanceof \Behat\Mink\Driver\Selenium2Driver) {
            $script = 'return (0 === jQuery.active)';
        }
        $this->spin(
            function($context) use($script) {
                /** @param FeatureContext $context */
                return $context->getSession()->evaluateScript($script);
            },
            ($duration/1000)
        );
    }
}