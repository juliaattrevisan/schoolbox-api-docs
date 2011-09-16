<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Component_Dashboard_ControllerAbstract extends Component_ControllerAbstract {
    protected function getTemplate ($templateName = '', $templatePath = '') {

        if ( empty($templatePath) ) {
            $templatePath = "component/dashboard";
        }

        return parent::getTemplate($templateName, $templatePath);
    }
}