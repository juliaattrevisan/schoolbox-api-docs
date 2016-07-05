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

        $this->contextPermission = FULL_ACCESS;

        if($this->isUsingAltSource()){
            $g = new Group;
            $this->contentPermission = min($g->getPermission($this->getSource()->getId()), $this->getMaximumPermission());
        } else {
            $this->contentPermission = FULL_ACCESS;
        }
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // Hide Dashboard components if the folder they pull data is soft-deleted or user lacks perm.
        return $this->isSourceRenderable();
    }

    protected function getTemplate($templateName = '', $templatePath = '')
    {

        if (empty($templatePath)) {
            $templatePath = "component/dashboard";
        }

        return parent::getTemplate($templateName, $templatePath);
    }
}