<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Component_Homepage_ControllerAbstract extends Component_ControllerAbstract {

    public function setup(array $context, array $config) {
        parent::setup($context, $config);
        $g = new Group();
        $this->permission = $_SESSION['su'] ? FULL_ACCESS : $g->getPermission($context['id']);
        $this->sitepage->setVar('ID', $context['id']);
    }

    /**
     * @param string $templateName
     * @param string $templatePath
     * @return string
     */
    protected function getTemplate ($templateName = '', $templatePath = '') {

        if ( empty($templatePath) ) {
            $templatePath = "component/homepage";
        }

        return parent::getTemplate($templateName, $templatePath);
    }


}
