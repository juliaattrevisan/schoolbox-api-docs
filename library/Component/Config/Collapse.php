<?php

/**
 * Component_Config_Collapse
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Component_Config_Collapse extends Component_Config_Abstract
{
    public const KEY = 'collapse';

    /**
     * Parse out config options
     *
     * @param SitePage $page
     * @param string $key
     * @param string $keyblock
     * @param mixed $configValue
     * @param Component_ControllerAbstract|null $component
     * @internal param array $allConfigs
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        if ($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_CONFIG_COLLAPSE_SELECTED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_COLLAPSE_SELECTED' => ''
            ));
        }
    }
}
