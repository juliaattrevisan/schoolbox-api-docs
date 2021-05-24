<?php

/**
 * Contains test steps that are intended to be generic across many tests.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_CoreContext extends BaseContext
{

    /**
     * Resets the database to a known clean state.
     *
     * The database state no longer resets after each scenario (I want to build
     * upon each scenario), so please call this as the first step in the first
     * scenario in a feature.
     *
     * @Given /^(?:|I )start afresh$/
     * @Given /^(?:|I )reset the database$/
     * @Given /^(?:|I )wipe the slate clean$/
     */
    public function startAfresh()
    {
        $this->getMinkContext()->visit('/test/reset.php');
    }

    /**
     * Placeholder step for not clearing the database when at the start of a
     * feature description.
     *
     * This method does nothing, but is included for clarity when defining
     * a scenario that continues on from the previous scenario.
     *
     * @Given /^(?:|I )do not start afresh$/
     * @Given /^(?:|I )do not reset the database$/
     * @Given /^(?:|I )do not wipe the slate clean$/
     * @Given /^(?:|I )continue from last scenario$/
     */
    public function doNotStartAfresh()
    {
    }

    /**
     * Logs in user.
     *
     * This represents a successful login, and will throw exception on a failed
     * login. For a login attempt, @see $this->loginAttempt()
     *
     * @Given /^(?:|I am) logged in as "(?P<username>[^"]*)" with password "(?P<password>[^"]*)"$/
     * @Given /^(?:|I )log in as "(?P<username>[^"]*)" with password "(?P<password>[^"]*)"$/
     *
     * @param string $username
     * @param string $password
     * @throws Exception if login failed
     */
    public function login($username, $password)
    {
        $this->loginAttempt($username, $password);
        $this->waitForLink('go-msg', 10000);
    }

    /**
     * Attempts to log in.
     *
     * This represents any login attempt, successful or not.
     *
     * @Given /^(?:|I )try to log in as "(?P<username>[^"]*)" with password "(?P<password>[^"]*)"$/
     *
     * @param string $username
     * @param string $password
     * @throws Exception if either username or password input aren't found
     */
    public function loginAttempt($username, $password)
    {
        // Logout first
        $this->logout();
        $page = $this->getSession()->getPage();
        $usernameInput = $page->findById('username');
        if (!($usernameInput instanceof Element\NodeElement)) {
            throw new Exception('Username input not found - is user already logged in?');
        }
        $passwordInput = $page->findById('password');
        if (!($passwordInput instanceof Element\NodeElement)) {
            throw new Exception('Password input not found - is user already logged in?');
        }
        $usernameInput->setValue($username);
        $passwordInput->setValue($password);
        $page->find('css', 'input.login')->press();
    }

    /**
     * Clicks a logout link.
     *
     * @Given /^(?:|I am) logged out$/
     * @Given /^(?:|I )log out$/
     */
    public function logout()
    {
        $this->getMinkContext()->visit('/logout.php');
    }

    /**
     * Visits a homepage, given a folder path from the resource browser.
     *
     * @Then /^I go to homepage "(?P<homepage_path>[^"]*)"$/
     *
     * @param string $homepage_path
     * @throws Exception if the folder was not found.
     */
    public function goToHomepage($homepage_path)
    {
        $this->getMinkContext()->visit('/resources/');
        $this->traverseTree($this->getSession()->getPage()->find('css', '#category-window'), $homepage_path);
        $this->getCoreContext()->waitForAjax();
        $this->getSession()->getPage()->find('css', '#detail-window a.rhomepage')->click();
    }

    /**
     * Visits the add a folder page, given a folder path from the resource browser.
     *
     * @Then /^I add a folder to "(?P<homepage_path>[^"]*)"$/
     *
     * @param string $homepage_path
     * @throws Exception if the folder was not found.
     */
    public function addFolderToHomepage($homepage_path)
    {
        $this->getMinkContext()->visit('/resources/');
        $this->traverseTree($this->getSession()->getPage()->find('css', '#category-window'), $homepage_path);
        $this->getCoreContext()->waitForAjax();
        $this->getSession()->getPage()->find('css', '#detail-window a.add')->click();
    }

    /**
     * Visits the modify folder page, given a folder path from the resource browser.
     *
     * @Then /^I modify folder "(?P<homepage_path>[^"]*)"$/
     *
     * @param string $homepage_path
     * @throws Exception if the folder was not found.
     */
    public function modifyFolder($homepage_path)
    {
        $this->getMinkContext()->visit('/resources/');
        $this->traverseTree($this->getSession()->getPage()->find('css', '#category-window'), $homepage_path);
        $this->getCoreContext()->waitForAjax();
        $this->getSession()->getPage()->find('css', '#detail-window a.modify')->click();
    }

    /**
     * Traverses a tree structure (examples: resource browser)
     *
     * @param Element\NodeElement $tree The element which contains the tree.
     * @param string              $path The path to traverse in the tree - each
     *                                  node is separated by a / character.
     * @return Element\NodeElement The node that represents the end of the path
     * @throws Exception if the end node couldn't be found
     */
    public function traverseTree($tree, $path)
    {

        $dirname_list      = array_filter(array_map('trim', explode('/', $path)), 'strlen');
        $current_folder_id = 0;

        $count = count($dirname_list);
        foreach ($dirname_list as $i => $dirname) {
            // Click the expand button, if there is one and it isn't expanded.
            // If this is the last item, click "visit homepage" instead.
            /** @var Element\NodeElement[] $child_folder_list */
            $child_folder_list = $tree->findAll('css', '.child-of-node-' . $current_folder_id);
            $is_dirname_found  = false;
            foreach ($child_folder_list as $child_folder) {
                $label       = $child_folder->find('css', '.label');
                $folder_name = $label->getText();
                $is_expanded = $child_folder->hasClass('expanded');

                if ($dirname === $folder_name) {
                    $is_dirname_found = true;
                    list(, $current_folder_id) = explode('-', $child_folder->getAttribute('id'));
                    if ($i < ($count - 1)) {
                        // Not last folder: drill down to next in tree
                        if (!$is_expanded) {
                            $child_folder->find('css', '.elbow')->click();
                            $this->getCoreContext()->waitForAjax();
                        }
                    } else {
                        // Last folder: return it
                        $label->click();
                        return $label;
                    }
                    break;
                }
            }
            if (!$is_dirname_found) {
                throw new Exception(
                    sprintf(
                        'Could not find folder "%s" in path "%s"',
                        $dirname,
                        implode('/', array_slice($dirname_list, 0, $i))
                    )
                );
            }
        }
        throw new Exception(
            'Could not find folder "%s" in path "%s"'
        );
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
     */
    public function setConfigurationSetting($type, $name, $value)
    {
        $this->getMinkContext()->visit('/adminv2/setting/' . $type);
        $form  = $this->getSession()->getPage()->find('css', 'form#settings');
        $field = $this->findConfigurationField($form, $name);
        if (!$field) {
            throw new Exception(sprintf('Could not find setting "%s:%s"', $type, $name));
        }
        $field->setValue($value);
        $form->findButton('Save')->press();
    }

    /**
     * Sets a config setting in the admin section.
     *
     * @Then /^(?:|I )set resource (?:config|setting|configuration) "(?P<type>[^:]*):(?P<name>[^"]*)" to "(?P<value>[^"]*)"$/
     *
     * @param string $type
     * @param string $name
     * @param string $value
     * @throws Exception if the form was not found.
     */
    public function setResourceConfigurationSetting($type, $name, $value)
    {
        $this->getMinkContext()->visit('/adminv2/setting/' . $type);
        $form  = $this->getSession()->getPage()->find('css', 'form#settings');
        $field = $this->findConfigurationField($form, $name);
        if (!$field) {
            throw new Exception(sprintf('Could not find setting "%s:%s"', $type, $name));
        }

        // Click the button
        // Search for the item in the resource builder
        $button = $form->find('css', 'input[data-input_name="' . $field->getAttribute('name') . '"]');
        $button->click();
        $this->waitForAjax();

        // Traverse the browser tree
        $browser = $this->getSession()->getPage()->findById('resource-browser');
        $this->traverseTree($browser, $value);
        $browser->findById('select-resource')->click();
        $form->findButton('Save')->press();
    }

    /**
     * Finds the input field for a configuration setting.
     *
     * @param Element\NodeElement $form The form to search in.
     * @param string              $name The label or name of the field.
     *
     * @return Element\NodeElement
     */
    protected function findConfigurationField($form, $name)
    {
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
        return $field;
    }

    /**
     * Waits for an element with specified CSS to exist on this page.
     *
     * Required because Selenium doesn't wait for the page to load by default
     * when clicking a link. This isn't a bad thing to have anyway, what with
     * AJAX requests and all that.
     *
     * @see MinkContext->assertElementOnPage()
     *
     * @Then /^(?:|I )wait for an? "(?P<element>[^"]*)" element$/
     * @Then /^(?:|I )wait (?P<duration>\d+)ms for an? "(?P<element>[^"]*)" element$/
     */
    public function waitForElementOnPage($element, $duration = 5000)
    {
        $this->spin(
            function ($context) use ($element) {
                /** @param FeatureContext $context */
                $context->getMinkContext()->assertElementOnPage($element);
                return true;
            },
            ($duration / 1000)
        );
    }

    /**
     * Waits for link on page.
     *
     * @Then /^(?:|I )wait for link "(?P<text>(?:[^"]|\\")*)"$/
     * @Then /^(?:|I )wait (?P<duration>\d+)ms for link "(?P<text>(?:[^"]|\\")*)"$/
     */
    public function waitForLink($link, $duration = 5000)
    {
        $this->spin(
            function ($context) use ($link) {
                /** @param FeatureContext $context */
                return $context->getSession()->getPage()->findLink($link) instanceof Element\NodeElement;
            },
            ($duration / 1000)
        );
    }

    /**
     * Waits for text on page.
     *
     * Required because Selenium doesn't wait for the page to load by default
     * when clicking a link. This isn't a bad thing to have anyway, what with
     * AJAX requests and all that.
     *
     * @see MinkContext->assertElementOnPage()
     *
     * @Then /^(?:|I )wait for "(?P<text>(?:[^"]|\\")*)"$/
     * @Then /^(?:|I )wait (?P<duration>\d+)ms for "(?P<text>(?:[^"]|\\")*)"$/
     */
    public function waitForText($text, $duration = 5000)
    {

        $this->spin(
            function ($context) use ($text) {
                /** @param FeatureContext $context */
                $context->getMinkContext()->assertPageContainsText($text);
                return true;
            },
            ($duration / 1000)
        );
    }

    /**
     * Waits for an element with specified CSS to exist on this page.
     *
     * Required because Selenium doesn't wait for the page to load by default
     * when clicking a link. This isn't a bad thing to have anyway, what with
     * AJAX requests and all that.
     *
     * @see MinkContext->fillField()
     *
     * @Then /^(?:|I )wait for an? "(?P<field>[^"]*)" field$/
     * @Then /^(?:|I )wait (?P<duration>\d+)ms for an? "(?P<field>[^"]*)" field$/
     */
    public function waitForFieldOnPage($field, $duration = 5000)
    {
        $this->spin(
            function ($context) use ($field) {
                /** @param FeatureContext $context */
                $element = $context->getSession()->getPage()->findField($field);
                return $element instanceof Element\NodeElement;
            },
            ($duration / 1000)
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
    public function waitForAjax($duration = 5000)
    {
        //
        // wat. Sahi fails with a return in the script to evaluate,
        // Selenium fails without one. FAIL
        $script = '(0 === jQuery.active)';
        if ($this->getSession()->getDriver() instanceof \Behat\Mink\Driver\Selenium2Driver) {
            $script = 'return (0 === jQuery.active)';
        }
        $this->spin(
            function ($context) use ($script) {
                /** @param FeatureContext $context */
                return $context->getSession()->evaluateScript($script);
            },
            ($duration / 1000)
        );
    }

    /**
     * Waits for an element to be visible. (The element must exist on the page.)
     *
     * @see MinkContext->assertElementOnPage()
     *
     * @Then /^(?:|I )wait for an? "(?P<element>[^"]*)" element to be visible$/
     * @Then /^(?:|I )wait (?P<duration>\d+)ms for an? "(?P<element>[^"]*)" element to be visible$/
     */
    public function waitForVisible($element, $duration = 5000)
    {
        $node = $this->getSession()->getPage()->find('css', $element);
        if (!($element instanceof Element\NodeElement)) {
            throw new Exception("Element \"$element\" was not found on page");
        }
        $this->delayUntilVisible($node, $duration / 1000);
    }

    /**
     * Version of fillField() that works with WYSIWYG (e.g. TinyMCE) textareas.
     * Required because Selenium refuses to fill in elements that are invisible.
     *
     * @see MinkContext->fillField()
     *
     * @When /^(?:|I )fill in wysiwyg "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
     * @When /^(?:|I )fill in wysiwyg "(?P<field>(?:[^"]|\\")*)" with:$/
     * @When /^(?:|I )fill in wysiwyg "(?P<value>(?:[^"]|\\")*)" for "(?P<field>(?:[^"]|\\")*)"$/
     */
    public function fillWysiwygField($field, $value)
    {
        try {
            $this->getMinkContext()->fillField($field, $value);
        } catch (\Behat\Mink\Exception\ElementNotFoundException $e) {
            throw $e;
        } catch (\Behat\Mink\Exception\ElementException $e) {
            // Element probably wasn't found because it was invisible.
            // Fill it with JS.
            $script  = sprintf('return (tinyMCE.get(%s).setContent(%s) || true)', json_encode($field), json_encode($value));
            $this->getSession()->executeScript($script);
        } catch (Exception $e) {
            throw $e;
        }
        // If it worked, this code would've used the "official" way to type into
        // wysiwyg boxes:
        // find the iframe, then type into its body.
//        $page  = $this->getSession()->getPage();
//        $field = $page->findField($field);
//        // Just use JS to set the value
//        $id = $field->getAttribute('id');
//        $iframe = $page->findById($id.'_ifr');
//        $this->getSession()->switchToIFrame($id.'_ifr');
//        $length = mb_strlen($value);
//        $body = $this->getSession()->getPage()->find('css', 'body');
//        for ($i = 0; $i < ($length-1); $i++) {
//            $body->keyPress(mb_substr($value, $i, 1));
//        }
//        $this->getSession()->switchToIFrame(null);
        return;
    }

    /**
     * Asserts that title matches a given pattern.
     *
     * @Then /^(?:|I ) should see title containing "(?P<title>(?:[^"]|\\")*)"$/
     * @Then /^title should contain "(?P<title>(?:[^"]|\\")*)"$/
     */
    public function assertTitle($title)
    {
        $actual_title = $this->getSession()->getPage()->find('xpath', '/head/title');
        if (strpos($actual_title->getText(), $title) === 0) {
            throw new Exception('Title did not match');
        }
    }

    /**
     * Triggers press of the Enter key. Commonly used for form submission,
     * especially on search forms.
     *
     * @When /^(?:|I )press Enter$/
     */
    public function pressEnterKey()
    {
        $this->getSession()->executeScript("jQuery(':focus').trigger(jQuery.Event('keypress', {which: 9}));");
    }

    /**
     * Clicks something. I can't believe this doesn't exist in the Mink context!
     *
     * @When /^(?:|I )click "(?P<link>[^"]*)"$/
     */
    public function clickLink($link)
    {
        $node = $this->getSession()->getPage()->find('css', $link);
        $node->click();
    }
}
