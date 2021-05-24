<?php

/**
 * Component_Config_Collaborate
 *
 * This is used in SocialStream
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Component_Config_Collaborate extends Component_Config_Abstract
{

    /**
     * Parse out config options
     *
     * @param SitePage                          $page
     * @param string                            $key
     * @param string                            $keyblock
     * @param mixed                             $configValue
     * @param Component_ControllerAbstract|null $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        if ($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_CONFIG_COLLABORATE_SELECTED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_COLLABORATE_SELECTED' => ''
            ));
        }
    }
}
