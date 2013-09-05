<?php
/**
 * Contains test steps for the course builder.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Behat\Mink\Element;

class Subcontext_CourseBuilderContext extends BaseContext {

    /**
     * Adds a task to the course builder component of this homepage.
     *
     * @Then /^(?:|I )add a task named "(?P<name>[^"]*)" to visit "(?P<url>[^"]*)"$/
     * @Then /^(?:|I )add a task named "(?P<name>[^"]*)"$/
     *
     * @param  string $name
     * @param  string $url
     * @param  bool   $requires_confirmation
     * @throws Exception if task list or button isn't found
     */
    public function addTask($name, $url = '', $requires_confirmation = false) {
        $component = $this->findCourseBuilderComponent();
        $button    = $component->find('css', '.add_buttons a[data-form_class="for_task"]');
        if (!($button instanceof Element\NodeElement)) {
            throw new Exception('Add task button not found');
        }
        $button->click();
        $form = $component->find('css', '.for_task form');
        $form->fillField('title', $name);
        // Somehow be able to use this for file uploads too.
        $form->fillField('link',  $url);
        if ($requires_confirmation) {
            $form->checkField('requires_confirmation');
        }
        $form->find('css', 'input[type="submit"]')->press();
        $this->getCoreContext()->waitForAjax();
    }

    /**
     * Adds a task to the course builder component of this homepage.
     *
     * @Then /^(?:|I )add a task named "(?P<name>[^"]*)" to visit "(?P<url>[^"]*)" which requires confirmation$/
     * @Then /^(?:|I )add a task named "(?P<name>[^"]*)" which requires confirmation$/
     *
     * @param string $name
     * @param string $url
     */
    public function addTaskRequiringConfirmation($name, $url = '') {
        $this->addTask($name, $url, true);
    }

    /**
     * Starts to add a due work task.
     *
     * @Then /^(?:|I )add a due work task/
     * @Then /^(?:|I )add a due work task named "(?P<name>[^"]*)"$/
     *
     * @param string $name
     * @throws Exception if task list or button isn't found
     */
    public function addDueWorkTask($name = '') {
        $component = $this->findCourseBuilderComponent();
        $button    = $component->find('css', '.add_buttons a[data-form_class="for_due_work"]');
        if (!($button instanceof Element\NodeElement)) {
            throw new Exception('Add due work button not found');
        }
        $button->click();
        // Element may not be visible yet
        $this->spin(
            function($context) use($component) {
                $component->find('css', '.for_due_work .interact a')->click();
                return true;
            },
            5
        );
        $this->getCoreContext()->waitForElementOnPage('form#createSubmissionForm');
        $form = $this->getSession()->getPage()->findById('createSubmissionForm');
        $form->fillField('task_title', $name);
    }

    /**
     * Starts to add an online assessment task.
     *
     * @Then /^(?:|I )add an online assessment task$/
     * @Then /^(?:|I )add an online assessment task named "(?P<name>[^"]*)"$/
     *
     * @param string $name
     * @throws Exception if task list or button isn't found
     */
    public function addOnlineAssessmentTask($name = '') {
        $component = $this->findCourseBuilderComponent();
        $button    = $component->find('css', '.add_buttons a[data-form_class="for_assessment"]');
        if (!($button instanceof Element\NodeElement)) {
            throw new Exception('Add online assessment button not found');
        }
        $button->click();
        // Element may not be visible yet
        $this->spin(
            function($context) use($component) {
                $component->find('css', '.for_assessment .interact a.create')->click();
                return true;
            },
            5
        );
        $this->getCoreContext()->waitForElementOnPage('form#createAssess');
        $form = $this->getSession()->getPage()->findById('createAssess');
        $form->fillField('task_title', $name);
    }

    /**
     * Finds the course task listing component on the current homepage.
     *
     * @throws Exception if task list isn't found
     * @return Element\NodeElement
     */
    protected function findCourseBuilderComponent() {
        $component = $this->getSession()->getPage()->find('css', '.component.course_task_list');
        if (!($component instanceof Element\NodeElement)) {
            throw new Exception('Course task list component not found');
        }
        return $component;
    }
}