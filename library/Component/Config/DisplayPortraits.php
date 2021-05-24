<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_DisplayPortraits extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'display_p', 'display_p_block');
        $page->setVar('display_p_block', '');

        $options = array('grid', 'list');

        foreach ($options as $o) {
            $page->setVar('COMPONENT_CONFIG_DISPLAYPORTRAITS_NAME', ucfirst($o));
            $page->setVar('COMPONENT_CONFIG_DISPLAYPORTRAITS_VALUE', $o);
            $page->setVar('COMPONENT_CONFIG_DISPLAYPORTRAITS_SELECTED', $o == $configValue ? ' selected="selected"' : '');
            $page->parse('display_p_block', 'display_p', true);
        }
    }
}
