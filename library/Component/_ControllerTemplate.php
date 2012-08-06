<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_{MODULE}_{COMPONENT}Controller extends Component_{MODULE}_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

        // TODO: Implement execute() method.

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, '{COMPONENT_TEMPLATE}')) {
            return false;
        }
        $this->parseConfig();

        // TODO: Implement renderTemplate() method.


        if($everythingWentAccordingToPlan) {
            return true; // everything went according to plan
        }
        else {
            return false; // will suppress the output of this component instance
        }
    }

}
