<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Size implements Component_Config_Interface {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'sizes', 'sizes_block');
        $page->setVar('sizes_block', '');

        for($i = 0; $i <= 10; $i++) {
            $page->setVar('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
            $page->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
            $page->setVar('COMPONENT_CONFIG_SIZE_SELECTED', $i == $configValue ? ' selected="selected"' : '');
            $page->parse('sizes_block', 'sizes', true);
        }

    }

}
