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
        if (preg_match('/^adminv2/.*$/i', $path, $matches)) {
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
//        $this->printDebug('Waiting for AJAX requests to complete...');
        $ajax_finished_condition = '(0 === jQuery.active)';
        $this->getSession()->wait($duration, $ajax_finished_condition);
        if (!$this->getSession()->evaluateScript($ajax_finished_condition)) {
            throw new Exception('Ajax wait timed out');
        }
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

}
