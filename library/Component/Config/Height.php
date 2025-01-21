<?php

/**
 * Class Component_Config_Height
 *
 * To set a custom height on a component
 */
class Component_Config_Height extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar('COMPONENT_CONFIG_HEIGHT', $configValue);
    }
}
