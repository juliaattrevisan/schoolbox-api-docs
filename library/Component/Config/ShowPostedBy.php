<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_ShowPostedBy implements Component_Config_Interface {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {
        $page->setVar('COMPONENT_CONFIG_SHOWPOSTEDBY_SELECTED', ($configValue == '1' ? 'checked="checked"' : ''));
    }

}
