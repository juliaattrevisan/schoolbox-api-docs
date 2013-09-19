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
     * @param  bool   $self_assess
     * @throws Exception if task list or button isn't found
     */
    public function addTask($name, $url = '', $self_assess = true) {
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
        // Requires confirmation field: now labeled "self-assess", and the other
        // way around (checked = doesn't require confirmation)
        if ($self_assess) {
            $form->checkField('self_assess');
        }
        else {
            $form->uncheckField('self_assess');
        }
        $form->find('css', 'input[type="submit"]')->press();
        $this->getCoreContext()->waitForAjax();
    }

    /**
     * Adds a task to the course builder component of this homepage.
     *
     * @Then /^(?:|I )add a task named "(?P<name>[^"]*)" to visit "(?P<url>[^"]*)" which is not self-assess$/
     * @Then /^(?:|I )add a task named "(?P<name>[^"]*)" which is not self-assess$/
     *
     * @param string $name
     * @param string $url
     */
    public function addNonSelfAssessTask($name, $url = '') {
        $this->addTask($name, $url, false);
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