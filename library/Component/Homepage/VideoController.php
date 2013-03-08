<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Component_Homepage_VideoController extends Component_Homepage_ControllerAbstract {


    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'video')) {
            return false;
        }
        $this->parseConfig();

        // Set up component name
        $this->setVar(array(
            'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Video',
        ));

        return true; // everything went according to plan

    }

}
