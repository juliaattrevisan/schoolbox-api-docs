<?php

/**
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_ShowClosed extends Component_Config_Abstract
{
    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        if ($configValue == '1') {
            $page->setVar([
                'COMPONENT_SHOWCLOSED_HIDE'            => 'hide',
                'COMPONENT_SHOWCLOSED_SHOW'            => '',
                'COMPONENT_CONFIG_SHOWCLOSED_SELECTED' => 'checked="checked"'
            ]);
        } else {
            $page->setVar([
                'COMPONENT_SHOWCLOSED_HIDE'            => '',
                'COMPONENT_SHOWCLOSED_SHOW'            => 'hide',
                'COMPONENT_CONFIG_SHOWCLOSED_SELECTED' => ''
            ]);
        }
    }
}
