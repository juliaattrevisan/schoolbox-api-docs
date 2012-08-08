<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_CompactMode implements Component_Config_Interface {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        if($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_COMPACTMODE'                 => 'compact-mode',
                'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
            ));
        }
        else {
            $page->setVar(array(
                'COMPONENT_COMPACTMODE'                 => '',
                'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
            ));
        }
    }
}
