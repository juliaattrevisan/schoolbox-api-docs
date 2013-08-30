<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Element;

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
        //
        // Reset the database and filesystem back to a well-known state
    }

    /**
     * Resets the database to a known clean state.
     *
     * @BeforeScenario
     */
    public function resetDatabase(Behat\Behat\Event\ScenarioEvent $event) {
//        $this->printDebug('Resetting database');
        $this->visit('/test/reset.php');
    }

    /**
     * Resets the browser.
     *
     * @AfterScenario
     */
    public function resetBrowser(Behat\Behat\Event\ScenarioEvent $event) {
//        $this->printDebug('Resetting browser session');
        $this->getSession()->reset();
    }

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
        $this->visit('/resources/');
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
                        $this->waitForAjax();
                    }
                    else {
                        // Last folder: open node, click "View Homepage"
                        $label->click();
                        $this->waitForAjax();
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
        $this->visit('/adminv2/setting/'.$type);
        $form = $this->getSession()->getPage()->find('css', 'form#settings');

        // Name could be either a label text or field name, try field name first.
        $field = $form->findField($name);
        if (!$field) {
            /** @var Element\NodeElement[] $labels */
            $labels = $form->findAll('css', 'label');
            foreach ($labels as $label) {
                // Anything after beginning of a tag isn't part of field name
                $label_name = trim(preg_replace('/<.*/', '', $label->getHtml()));
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
     * Creates a new template on either the homepage, eportfolio or dashboard
     * pages.
     *
     * @Given /^I create a template "([^"]*)"$/
     * @param string $name The name of the template.
     */
    public function createTemplate($name) {
        $form = $this->getSession()->getPage()->findById('template-action');
        $form->findField('name')->setValue($name);
        $form->findButton('New')->press();
        //
        // Once loaded - select this button and wait for it to show.
        // A new item won't have anything there.
        $template_field = $form->findField('template');
        $option_list    = $template_field->findAll('css', 'option');
        $option         = array_pop($option_list);
        /** @var Element\NodeElement $option */
        $template_field->selectOption($option->getAttribute('value'));
        $this->waitForAjax();
    }

    /**
     * Adds a component with the given name to the component template.
     *
     * @Given /^I add "([^"]*)" to "([^"]*)" at position "([^"]*)"$/
     * @param string $component The name of the component to add
     * @param string $section   The section to add the component in (top, left, right)
     * @param string $position  The position to load the component into
     * @throws Exception if the context to add the component to, or the
     *                   component, couldn't be defined
     */
    public function addComponentToTemplate($component, $section, $position) {
        //
        // Forge a post request
        // context: from URL
        // definitionId: From selected component
        // targetId: whatever the current selected template is
        $path    = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH);
        $context = null;
        if (preg_match('/^\\/adminv2\\/(.*$)/i', $path, $matches)) {
            list(, $context) = $matches;
        }
        if (!$context) {
            throw new Exception('Could not determine context');
        }

        $page = $this->getSession()->getPage();
        $drag_target = null;
        foreach ($page->findAll('css', '#definition-palette .component') as $potential_target) {
            /** @var Element\NodeElement $potential_target */
            if ($potential_target->getText() === $component) {
                $drag_target = $potential_target;
                break;
            }
        }
        if (!($drag_target instanceof Element\NodeElement)) {
            throw new Exception(sprintf('Could not find component of name "%s"', $component));
        }

        $class = $drag_target->getAttribute('class');
        $definition_id = null;
        if (preg_match('/definition-id-(\\d+)/', $class, $matches)) {
            list(, $definition_id) = $matches;
        }
        if (!$definition_id) {
            throw new Exception('Could not find component definition ID');
        }

        $form = $this->getSession()->getPage()->findById('template-action');
        $template_id = $form->findField('template')->getValue();

        $this->ajaxPost(
            '/adminv2/component/save',
            array(
                'context'      => 'eportfolio',
                'definitionId' => $definition_id,
                'targetId'     => $template_id,
                'position'     => $section,
                'sequence'     => $position,
            )
        );

        //
        // Drag and drop - if only that worked.
//        $drag_target = null;
//        foreach ($page->findAll('css', '#definition-palette .component') as $potential_target) {
//            /** @var Element\NodeElement $potential_target */
//            if ($potential_target->getText() === $component) {
//                $drag_target = $potential_target;
//                break;
//            }
//        }
//        if (!($drag_target instanceof Element\NodeElement)) {
//            throw new Exception(sprintf('Could not find component of name "%s"', $component));
//        }
//        $drop_target = $page->findById('position-'.$section);
//
//        //
//        // Exact location within $drop_target depends on $position.
//        $potential_drop_targets = $drop_target->findAll('css', '.component');
//        $drop_index = intval($position-1);
//        if (isset($potential_drop_targets[$drop_index])) {
//            $drop_target = $potential_drop_targets[$drop_index];
//        }
//        $drag_target->dragTo($drop_target);
    }

    /**
     * @param string $section   The section the component is in (top, left, right)
     * @param string $position  The position to load the component into
     *
     * @return Element\NodeElement
     * @throws Exception if the form wasn't found
     */
    protected function getComponentForm($section, $position) {
        foreach ($this->getSession()->getPage()->findAll('css', "#position-$section .component") as $i => $base_component) {
            /** @var Element\NodeElement $base_component */
            if ($i+1 == $position) {
                //
                // Selenium throws a wobbley if you try to use an invisible
                // element. Show it, then.
                // Kill off animations so there's less chance of hitting button
                // in the middle of transition (meaning element is visible but
                // becoming invisible)
                $this->disableAnimation();
                if (!$base_component->find('css', '.editPanel')->isVisible()) {
                    $base_component->find('css', '.config-this')->click();
                }
                return $base_component->find('css', 'form');
            }
        }
        throw new Exception(sprintf('Could not find component at position "%s:%d"', $section, $position));
    }

    /**
     * @Given /^I rename component on "([^"]*)" at position "([^"]*)" to "([^"]*)"$/
     * @param string $section   The section the component is in (top, left, right)
     * @param string $position  The position to load the component into
     * @param string $new_name  The name of the new component
     */
    public function renameComponent($section, $position, $new_name) {
        $form = $this->getComponentForm($section, $position);
        $form->findField('componentname')->setValue($new_name);
        $form->findButton('Update')->press();
        $this->waitForAjax();
    }

    /**
     * @Given /^I repurpose component on "([^"]*)" at position "([^"]*)" to "([^"]*)"$/
     * @param string $section     The section the component is in (top, left, right)
     * @param string $position    The position to load the component into
     * @param string $new_purpose The new purpose of the component
     */
    public function repurposeComponent($section, $position, $new_purpose) {
        $form = $this->getComponentForm($section, $position);
        $form->findField('description')->setValue($new_purpose);
        $form->findButton('Update')->press();
        $this->waitForAjax();
    }

    /**
     * Checks that a component exists in the given position on the current page.
     *
     * @Then /^I see component "([^"]*)" on "([^"]*)" in position "([^"]*)"$/
     */
    public function verifyComponentInPosition($name, $section, $position) {

        foreach ($this->getSession()->getPage()->findAll('css', "#content .homepage .$section .box") as $i => $box) {
            /** @var Element\NodeElement $box */
            if ($i+1 == $position) {
                //
                // Search for $name
                $actual_name = $box->find('css', 'h2')->getText();
                if (trim($actual_name) !== trim($name)) {
                    throw new Exception(
                        sprintf(
                            'Component at position "%s:%d" is named "%s" instead of "%s"',
                            $section, $position, $actual_name, $name
                        )
                    );
                }
                return true;
            }
        }
        throw new Exception(sprintf('Could not find component at position "%s:%d"', $section, $position));
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
                $context->assertElementOnPage($element);
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

    /**
     * Switches off all jQuery animation - all transiations now run immediately.
     * Required for some tests when using Selenium (which doesn't work when
     * manipulating invisible elements - what if an element is transitioning
     * from visible to invisible?)
     */
    protected function disableAnimation() {
        $this->getSession()->executeScript('jQuery.fx.off = true');
    }

    /**
     * Sends a POST request through jQuery.
     *
     * @param string $url       The URL to post to.
     * @param array  $post_vars An array of post variables to send.
     */
    protected function ajaxPost($url, $post_vars) {
//        $this->printDebug(sprintf('Post to "%s"', $url));
        $this->getSession()->executeScript(
            sprintf('jQuery.post(%s, %s)', json_encode($url), json_encode($post_vars))
        );
    }

    /**
     * Attempts to run $callback($this) once per second for $timeout seconds.
     * Returns true as soon as this callback returns true.
     * Any exceptions the callback throws are ignored.
     *
     * @see http://docs.behat.org/cookbook/using_spin_functions.html
     *
     * @param  callable  $callback
     * @param  int       $timeout
     * @throws Exception if $timeout is reached and the callback still hasn't
     *                   returned true.
     * @return bool
     */
    protected function spin($callback, $timeout = 15) {
        for ($i = 0; $i < $timeout; $i++) {
            try {
                if ($callback($this)) {
                    return true;
                }
            }
            catch (Exception $e) {
                // do nothing
            }
            sleep(1);
        }

        $backtrace = debug_backtrace();

        throw new Exception(
            "Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function'] . "()\n" .
            $backtrace[1]['file'] . ", line " . $backtrace[1]['line']
        );
    }

}
