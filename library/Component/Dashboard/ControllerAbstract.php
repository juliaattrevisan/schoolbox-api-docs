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
        // if you're able to see the component, you're in admin mode so full access is granted
        $this->permission        = FULL_ACCESS;
        $this->controlPermission = FULL_ACCESS;

        // roll the config specified into what we already have
        if (count($config)) {
            foreach ($config as $k => $v) {
                $this->config[$k] = $v;
            }
        }

        $this->postConstruct();
    }

    protected function getTemplate($templateName = '', $templatePath = '')
    {

        if (empty($templatePath)) {
            $templatePath = "component/dashboard";
        }

        return parent::getTemplate($templateName, $templatePath);
    }
}