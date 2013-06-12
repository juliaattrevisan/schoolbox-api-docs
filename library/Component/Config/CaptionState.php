<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_CaptionState extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {
        //
        // Anything other than "active" gets set to "inactive" directly
        if (!($configValue == 'Active')) {
            $configValue = 'Inactive';
        }
        // has an internal block for the select element
        $page->setBlock($keyblock, 'caption_state', 'caption_state_block');
        $page->setVar('caption_state_block', '');

        foreach(array('On' => 'Active', 'Off' => 'Inactive') as $k => $v) {
            $page->setVar('COMPONENT_CONFIG_CAPTION_STATE_NAME', $k);
            $page->setVar('COMPONENT_CONFIG_CAPTION_STATE_VALUE', $v);
            $page->setVar('COMPONENT_CONFIG_CAPTION_STATE_SELECTED', $v == $configValue ? ' selected="selected"' : '');
            $page->parse('caption_state_block', 'caption_state', true);
        }

    }

}
