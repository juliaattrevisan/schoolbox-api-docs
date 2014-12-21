<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Src extends Component_Config_Abstract {

    /**
     * Strips /homepage/ prefix from $value if it's there.
     *
     * @param  array                        $value
     * @param  Component_ControllerAbstract $controller
     * @return string
     */
    public function dehydrate($value, Component_ControllerAbstract $controller) {
        preg_match("#[^0-9]*([0-9]*)#i", $value, $matches);
        if ($matches) {
            $value = $matches[1];
        }
        return $value;
    }

//    protected $is_shown_on_template = false;

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null) {
        $catHandler = new Category();
        $cat = $catHandler->getByID($configValue);

		if(empty($cat['name'])) {
			$page->setVar(array(
				'COMPONENT_CONFIG_SRC_ID'   => '#',
				'COMPONENT_CONFIG_SRC_NAME' => 'Local',
                'COMPONENT_CONFIG_SRC_CID'  => '0'
			));
		}
		else {
			$page->setVar(array(
	            'COMPONENT_CONFIG_SRC_ID'   => '/homepage/' . $cat['id'],
	            'COMPONENT_CONFIG_SRC_NAME' => $cat['name'],
                'COMPONENT_CONFIG_SRC_CID'  => $cat['id']
	        ));
		}
    }

}
