<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Component_Config_Align extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'aligns', 'aligns_block');
        $page->setVar('aligns_block', '');

        $values = array('left', 'center', 'right');

        foreach ($values as $value) {
            $page->setVar('COMPONENT_CONFIG_NAME', ucfirst($value));
            $page->setVar('COMPONENT_CONFIG_VALUE', $value);
            $page->setVar('COMPONENT_CONFIG_SELECTED', $value == $configValue ? ' selected="selected"' : '');
            $page->parse('aligns_block', 'aligns', true);
        }

    }

}
