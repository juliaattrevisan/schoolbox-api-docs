<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * This component will just output a standard set of HTML. Can be used in any scenario you need to output HTML without any processing.
 * To use this component a config option must be set with the component definition with a default value of the template you wish to use
 *
 */


class Component_Eportfolio_TestController extends Component_Eportfolio_ControllerAbstract {

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
    * @return bool
    */ 
   public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, $this->getConfig('template'))) {
            return false;
        }
   
        return true;
    }
    
}
