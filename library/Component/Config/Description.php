<?php
/**
 * Component_Config_Description
 *
 * This allows you to add an arbitrary input for a component
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Config_Description extends Component_Config_Abstract
{

    /**
     * Parse out this config option
     *
     * @param SitePage                          $page
     * @param string                            $key
     * @param string                            $keyblock
     * @param mixed                             $configValue
     * @param Component_ControllerAbstract|null $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar('COMPONENT_CONFIG_DESCRIPTION', $configValue);
    }
}
