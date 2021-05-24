<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_State extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        // Anything other than "active" gets set to "inactive" directly
        if (!($configValue == 'Active')) {
            $configValue = 'Inactive';
        }

        // has an internal block for the select element
        $page->setBlock($keyblock, 'chat_state', 'chat_state_block');
        $page->setVar('chat_state_block', '');

        foreach (array('on' => 'Active', 'off' => 'Inactive') as $k => $v) {
            $page->setVar('COMPONENT_CONFIG_CHAT_STATE', $v);
            $page->setVar('COMPONENT_CONFIG_CHAT_VALUE', $k);
            $page->setVar('COMPONENT_CONFIG_CHAT_STATE_SELECTED', $v == $configValue ? ' selected="selected"' : '');
            $page->parse('chat_state_block', 'chat_state', true);
        }
    }
}
