<?php

/**
 * Contains test steps for the course builder.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;
use Behat\Gherkin\Node\TableNode;

class Subcontext_CourseBuilderContext extends BaseContext
{

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
    public function addTask($name, $url = '', $self_assess = true)
    {
        $component = $this->findCourseBuilderComponent();
        $button    = $component->find('css', '.add_buttons a[data-form_class="for_task"]');
        if (!($button instanceof Element\NodeElement)) {
            throw new Exception('Add task button not found');
        }
        $button->click();
        $form = $component->find('css', '.for_task form');
        $this->delayUntilVisible($form);
        $form->fillField('title', $name);
        // Somehow be able to use this for file uploads too.
        if ($url) {
            $form->find('css', 'a[data-function="link"]')->click();
            $form->fillField('link', $url);
        }
        // Requires confirmation field: now labeled "self-assess", and the other
        // way around (checked = doesn't require confirmation)
        if ($self_assess) {
            $form->checkField('self_assess');
        } else {
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
    public function addNonSelfAssessTask($name, $url = '')
    {
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
    public function addDueWorkTask($name = '')
    {
        $component = $this->findCourseBuilderComponent();
        $button    = $component->find('css', '.add_buttons a[data-form_class="for_due_work"]');
        if (!($button instanceof Element\NodeElement)) {
            throw new Exception('Add due work button not found');
        }
        $button->click();
        $this->getCoreContext()->waitForElementOnPage('form#createSubmissionForm');
        $form = $this->getSession()->getPage()->findById('createSubmissionForm');
        $form->fillField('name', $name);
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
    public function addOnlineAssessmentTask($name = '')
    {
        $component = $this->findCourseBuilderComponent();
        $button    = $component->find('css', '.add_buttons a[data-form_class="for_assessment"]');
        if (!($button instanceof Element\NodeElement)) {
            throw new Exception('Add online assessment button not found');
        }
        $button->click();
        $this->getCoreContext()->waitForElementOnPage('form#createAssess');
        $form = $this->getSession()->getPage()->findById('createAssess');
        $form->fillField('title', $name);
    }

    /**
     * Selects a course for import.
     *
     * @Then /^(?:|I )import course "(?P<course_path>[^"]*)"$/
     * @Then /^(?:|I )import course "(?P<course_path>[^"]*)" on "(?P<section>[^"]*)" in position "(?P<position>[^"]*)$/
     *
     * @param string $course_path The course path, as shown in resource browser
     * @param string $section     The section the course component is in -
     *                            defaults to "left"
     * @param int    $position    The position the course component is in -
     *                            defaults to "1"
     */
    public function selectCourseForImport($course_path, $section = 'left', $position = 1)
    {
        /** @var Subcontext_ComponentContext $context */
        $context   = $this->getMainContext()->getSubcontextByClassName('Subcontext_ComponentContext');
        $component = $context->getComponentInPosition($section, $position);
        $component->find('css', '[data-interaction="homepage_selector"]')->click();
        $this->getCoreContext()->waitForAjax();
        $tree = $this->getSession()->getPage()->find('css', '#resource-browser');
        $this->getCoreContext()->traverseTree($tree, $course_path);
        $tree->findById('select-resource')->click();
    }

    /**
     * Sets the open date for the given unit when importing or saving it.
     *
     * @Then /^(?:|I )set open date for course unit "(?P<unit>[^"]*)" as "(?P<date>[^"]*)"$/
     *
     * @param  string $unit The name of the unit
     * @param  string $date The date to set (as a strtotime-recognised string)
     * @throws Exception if any required element is not found
     */
    public function setOpenDateForCourseUnit($unit, $date)
    {
        $this->setDateForCourseUnit($unit, $date, 'open_date');
    }

    /**
     * Sets the due date for the given unit when importing or saving it.
     *
     * @Then /^(?:|I )set due date for course unit "(?P<unit>[^"]*)" as "(?P<date>[^"]*)"$/
     *
     * @param  string $unit The name of the unit
     * @param  string $date The date to set (as a strtotime-recognised string)
     * @throws Exception if any required element is not found
     */
    public function setDueDateForCourseUnit($unit, $date)
    {
        $this->setDateForCourseUnit($unit, $date, 'due_date');
    }

    /**
     * Sets the due date for the given unit when importing or saving it.
     *
     * @param  string $unit The name of the unit
     * @param  string $date The date to set (as a strtotime-recognised string)
     * @param  string $name The name of the element to set
     * @throws Exception if any required element is not found
     */
    public function setDateForCourseUnit($unit, $date, $name)
    {
        $form = $this->getSession()->getPage()->find('css', 'form#importForm');
        if (!($form instanceof Element\NodeElement)) {
            throw new Exception("Course import form not found");
        }

        // Search for rows in the table
        /** @var Element\NodeElement[] $rows */
        $unit_row_list = $form->findAll('css', 'tr[data-type="unit_row"]');
        $row           = null;
        foreach ($unit_row_list as $unit_row) {
            if (stripos($unit_row->getText(), $unit) !== false) {
                $row = $unit_row;
                break;
            }
        }
        if (!($row instanceof Element\NodeElement)) {
            throw new Exception("Unit \"$unit\" not found in course import form");
        }

        $input = $row->find('css', 'input.' . $name);
        if (!($input instanceof Element\NodeElement)) {
            throw new Exception("Date input for unit \"$unit\" not found in course import form");
        }
        $date_object = new DateTime($date);
        $input->setValue($date_object->format('d/m/Y'));
    }

    /**
     * Finds the course task listing component on the current homepage.
     *
     * @throws Exception if task list isn't found
     * @return Element\NodeElement
     */
    protected function findCourseBuilderComponent()
    {
        $component = $this->getSession()->getPage()->find('css', '.component.course_task_list');
        if (!($component instanceof Element\NodeElement)) {
            throw new Exception('Course task list component not found');
        }
        return $component;
    }

    /**
     * Finds the course instance component on the current homepage.
     *
     * @throws Exception if it isn't found
     * @return Element\NodeElement
     */
    protected function findCourseInstanceComponent()
    {
        $component = $this->getSession()->getPage()->find('css', '.component.course_instance');
        if (!($component instanceof Element\NodeElement)) {
            throw new Exception('Course instance component not found');
        }
        return $component;
    }

    /**
     * Finds a course on the page.
     */
    protected function findCourse($name)
    {
        $component = $this->findCourseInstanceComponent();
        foreach ($component->findAll('css', '.unit') as $unit) {
            $unit_text = trim($unit->find('css', 'h3')->getText());
            if ($name === $unit_text) {
                return $unit;
            }
        }
        throw new Exception('Unit "' . $name . '" not found');
    }

    protected function findTasks(Element\NodeElement $course_element)
    {
        return $course_element->findAll('css', '.task-list .unit-task');
    }

    /**
     * Expands a course definition.
     *
     * @When /^I expand course "(?P<course>[^"]*)"$/
     */
    public function expandCourse($course)
    {
        $course_element = $this->findCourse($course);
        $toggler = $course_element->find('css', 'a[data-interaction="toggle-expanded"]');
        if (!$toggler) {
            throw new Exception('Unit "' . $course . '" toggler does not exist');
        }
        if ($course_element->hasClass('expanded')) {
            throw new Exception('Unit "' . $course . '" is already expanded');
        }
        $this->disableAnimation();
        $toggler->click();
    }

    /**
     * @Then /^course "(?P<course>[^"]*)" should have closed$/
     */
    public function courseShouldHaveClosed($course)
    {
        $course_element = $this->findCourse($course);
        $open_date      = $course_element->getAttribute('data-open_date');
        $due_date       = $course_element->getAttribute('data-due_date');
        $now            = date('Y-m-d');
        if ($now <= $due_date) {
            throw new Exception('Unit "' . $course . '" has not closed');
        }
    }

    /**
     * @Then /^course "(?P<course>[^"]*)" should still be open$/
     */
    public function courseShouldStillBeOpen($course)
    {
        $course_element = $this->findCourse($course);
        $open_date      = $course_element->getAttribute('data-open_date');
        $due_date       = $course_element->getAttribute('data-due_date');
        $now            = date('Y-m-d');
        if ($now > $due_date) {
            throw new Exception('Unit "' . $course . '" has closed');
        }
        if ($now < $open_date) {
            throw new Exception('Unit "' . $course . '" has not opened yet');
        }
    }

    /**
     * @Then /^course "(?P<course>[^"]*)" should not be open yet$/
     */
    public function courseShouldNotBeOpenYet($course)
    {
        $course_element = $this->findCourse($course);
        $open_date      = $course_element->getAttribute('data-open_date');
        $due_date       = $course_element->getAttribute('data-due_date');
        $now            = date('Y-m-d');
        if ($now >= $open_date) {
            throw new Exception('Unit "' . $course . '" has opened');
        }
    }

    /**
     * @Then /^course "(?P<course>[^"]*)" should have tasks:$/
     */
    public function courseShouldHaveTasks($course, TableNode $table)
    {
        $course_element = $this->findCourse($course);
        $task_list      = $this->findTasks($course_element);
        $expected_tasks = $table->getHash();
        if (($actual_count = count($task_list)) !== ($expected_count = count($expected_tasks))) {
            throw new Exception("Actual task count of $actual_count does not match expected count of $expected_count");
        }
        foreach ($task_list as $i => $task) {
            $actual_name   = trim($task->find('css', 'h4')->getText());
            $expected_name = trim($expected_tasks[$i]['name']);
            if ($actual_name !== $expected_name) {
                throw new Exception("Task $i \"$actual_name\" is different to expected \"$expected_name\"");
            }
        }
    }
}
