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
     * @param string $name
     * @param string $url
     * @param bool   $requires_confirmation
     */
    public function addTask($name, $url = '', $requires_confirmation = false) {
        // Type: task, online_assess, due_work
        // Name:
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
     * Adds a due work task to the course builder component of this homepage.
     *
     * @Then /^(?:|I )add a due work task named "(?P<name>[^"]*)"$/
     *
     * @param string $name
     */
    public function addDueWorkTask($name) {

    }

    /**
     * Adds an assessment task to the course builder component of this homepage.
     *
     * @Then /^(?:|I )add an online assessment task named "(?P<name>[^"]*)"$/
     *
     * @param string $name
     */
    public function addOnlineAssessmentTask($name) {

    }
}