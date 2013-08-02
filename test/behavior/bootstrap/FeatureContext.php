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

//    /**
//     * Resets the database to a known clean state.
//     *
//     * @BeforeScenario
//     */
//    public function resetDatabase(Behat\Behat\Event\ScenarioEvent $event) {
//        //
//        // Run the rebuild script through web.
//        $this->getSession()->visit('/test/reset.php');
//    }

    /**
     * Resets the browser.
     *
     * @AfterScenario
     */
    public function resetBrowser(Behat\Behat\Event\ScenarioEvent $event) {
        $this->getSession()->reset();
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
        $this->spin(
            function($context) {
                /** @param FeatureContext $context */
                return $context->getSession()->getPage()->findLink('Logout') instanceof Element\NodeElement;
            },
            10
        );
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
     * @Then /^I wait for AJAX$/
     * @Then /^I wait for AJAX to finish$/
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
