<?php
/**
 * Course component for a class.
 * Before a course is selected, it contains a selector for the course - after
 * this, it contains a list of units and tasks within the course.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Component_Homepage_CourseInstanceController extends Component_Homepage_ControllerAbstract {

    /**
     * {@inheritdoc}
     */
    public function execute() {
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'courseInstance')) {
            return false;
        }
        $this->parseConfig();

        // If courseHomepage is set, a course has been selected.
        // This means that the units and tasks that were imported are displayed.
        // If it isn't, then a selector is displayed (if user has write access)

        $this->setVar('COMPONENT_NAME', $this->getConfig('componentname'));

        return true;
    }

}