<?php
/**
 * Component_Dashboard_JuniorTimetableController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Dashboard_JuniorTimetableController extends Component_Dashboard_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        return true;
    }

    /**
     * Outputs HTML template defined by config variable 'template'
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'juniorTimetable')) {
            return false;
        }

        $this->parseConfig();

        return true;
    }
} 