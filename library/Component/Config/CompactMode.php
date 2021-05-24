<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_CompactMode extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        if ($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_COMPACTMODE_HIDE'            => 'hide',
                'COMPONENT_COMPACTMODE_SHOW'            => '',
                'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'COMPONENT_COMPACTMODE_HIDE'            => '',
                'COMPONENT_COMPACTMODE_SHOW'            => 'hide',
                'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
            ));
        }
    }
}
