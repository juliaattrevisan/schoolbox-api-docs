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
     * Adds a course builder task to this homepage.
     *
     * @Then /^(?:|I )add a task named "(?P<name>[^"]*)" to visit "(?P<url>[^"]*)"$/
     *
     * @param string $type
     * @param string $name
     * @param string $url
     * @param bool   $requires_confirmation
     */
    public function addTask($name, $type = 'task', $url = '', $requires_confirmation = false) {
        // Type: task, online_assess, due_work
        // Name:
    }

}