<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Src implements Component_Config_Interface {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {
        $catHandler = new Category();
        $cat = $catHandler->getByID($configValue);

        $page->setVar(array(
            'COMPONENT_CONFIG_SRC_ID'   => $cat['id'],
            'COMPONENT_CONFIG_SRC_NAME' => $cat['name'],
        ));

    }

}
