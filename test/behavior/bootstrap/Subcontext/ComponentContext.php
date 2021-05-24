<?php

/**
 * Contains test steps that relate to dashboards, homepages and eportfolios, and
 * the components on them.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_ComponentContext extends BaseContext
{

    /**
     * Creates a new template on either the homepage, eportfolio or dashboard
     * pages.
     *
     * @Given /^I create a template "([^"]*)"$/
     * @param string $name The name of the template.
     */
    public function createTemplate($name)
    {
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
        $this->getCoreContext()->waitForAjax();
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
    public function addComponentToTemplate($component, $section, $position)
    {
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
     * Adds a component to the current homepage.
     *
     * @Then /^(?:|I )add component "(?P<type>[^:]*):(?P<name>[^"]*)"$/
     *
     * @param string $type
     * @param string $name
     * @throws Exception if the component couldn't be found
     */
    public function addComponentToHomepage($type, $name)
    {
        // Show the component list
        $custom_button = $this->getSession()->getPage()->find('css', '.customise');
        if (!$custom_button) {
            throw new Exception('No customise button found');
        }
        $custom_button->click();

        // Find the component section of this type.
        /** @var Element\NodeElement[] $section_list */
        $section_id   = null;
        $section_list = $this->getSession()->getPage()->findAll('css', '#tabs-homepage .component-categories a');
        foreach ($section_list as $section) {
            $section_name = substr(trim($section->getHtml()), 0, strlen($type));
            if ($section_name === $type) {
                $section_id = parse_url($section->getAttribute('href'), PHP_URL_FRAGMENT);
                $section->click();
                break;
            }
        }
        if (!$section_id) {
            throw new Exception(sprintf('Component type "%s" was not found', $type));
        }

        /** @var Element\NodeElement[] $component_list */
        $component_list = $this->getSession()->getPage()->findAll('css', '#tabs-homepage #' . $section_id . ' .add-component');
        foreach ($component_list as $component) {
            $component_name = $this->getBeginningText($component->find('css', 'h4'));
            if ($component_name === $name) {
                $button = $component->find('css', 'a.add');
                if (!$button) {
                    throw new Exception(sprintf('Component of name "%s" has no add button', $name));
                }
                $button->click();
                $this->getCoreContext()->waitForAjax();
                return;
            }
        }
        throw new Exception(sprintf('No component of name "%s" exists', $name));
    }

    /**
     * @param string $section   The section the component is in (top, left, right)
     * @param string $position  The position to load the component into
     *
     * @return Element\NodeElement
     * @throws Exception if the form wasn't found
     */
    protected function getComponentForm($section, $position)
    {
        foreach ($this->getSession()->getPage()->findAll('css', "#position-$section .component") as $i => $base_component) {
            /** @var Element\NodeElement $base_component */
            if ($i + 1 == $position) {
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
    public function renameComponent($section, $position, $new_name)
    {
        $form = $this->getComponentForm($section, $position);
        $form->findField('componentname')->setValue($new_name);
        $form->findButton('Update')->press();
        $this->getCoreContext()->waitForAjax();
    }

    /**
     * @Given /^I repurpose component on "([^"]*)" at position "([^"]*)" to "([^"]*)"$/
     * @param string $section     The section the component is in (top, left, right)
     * @param string $position    The position to load the component into
     * @param string $new_purpose The new purpose of the component
     */
    public function repurposeComponent($section, $position, $new_purpose)
    {
        $form = $this->getComponentForm($section, $position);
        $form->findField('description')->setValue($new_purpose);
        $form->findButton('Update')->press();
        $this->getCoreContext()->waitForAjax();
    }

    /**
     * Retrieves the component that is in the requested section and position.
     *
     * @param  string $section  "top", "left" or "right"
     * @param  int    $position The position in the section to retrieve
     *                (1: first, 2: next, etc)
     * @throws Exception if no component at $section and $position
     * @return Element\NodeElement The box that contains the component.
     */
    public function getComponentInPosition($section, $position)
    {
        foreach ($this->getSession()->getPage()->findAll('css', "#content .homepage .$section .box") as $i => $box) {
            if ($i + 1 == $position) {
                return $box;
            }
        }
        throw new Exception(sprintf('Could not find component at position "%s:%d"', $section, $position));
    }

    /**
     * Checks that a component exists in the given position on the current page.
     *
     * @Then /^I see component "([^"]*)" on "([^"]*)" in position "([^"]*)"$/
     */
    public function verifyComponentInPosition($name, $section, $position)
    {
        $box         = $this->getComponentInPosition($section, $position);
        $actual_name = $box->find('css', 'h2')->getText();
        if (trim($actual_name) !== trim($name)) {
            throw new Exception(
                sprintf(
                    'Component at position "%s:%d" is named "%s" instead of "%s"',
                    $section,
                    $position,
                    $actual_name,
                    $name
                )
            );
        }
    }
}
