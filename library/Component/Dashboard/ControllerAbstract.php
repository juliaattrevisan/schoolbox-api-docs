<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */
abstract class Component_Dashboard_ControllerAbstract extends Component_ControllerAbstract
{
    /**
     * Sets up a dashboard component.
     *
     * @param array $context Ignored - dashboard components have no context
     * @param array $config
     */
    public function setup($context = null, array $config = array())
    {
        parent::setup(null, $config);

        // if you're able to see the component, you're in admin mode so full access is granted
        $this->contentPermission = FULL_ACCESS;
        $this->contextPermission = FULL_ACCESS;

        $this->postConstruct();
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if($this->isUsingAltSource() && $this->getSource()->isDeleted()){
            return false;
        }
        return true;
    }

    protected function getTemplate($templateName = '', $templatePath = '')
    {

        if (empty($templatePath)) {
            $templatePath = "component/dashboard";
        }

        return parent::getTemplate($templateName, $templatePath);
    }
}