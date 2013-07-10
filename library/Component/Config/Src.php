<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Src extends Component_Config_Abstract {


//    protected $is_shown_on_template = false;

	public function parse(SitePage $page, $key, $keyblock, $configValue) {
        $catHandler = new Category();
        $cat = $catHandler->getByID($configValue);

		if(empty($cat['name'])) {
			$page->setVar(array(
				'COMPONENT_CONFIG_SRC_ID'   => '#',
				'COMPONENT_CONFIG_SRC_NAME' => 'Local',
			));
		}
		else {
			$page->setVar(array(
	            'COMPONENT_CONFIG_SRC_ID'   => '/homepage/' . $cat['id'],
	            'COMPONENT_CONFIG_SRC_NAME' => $cat['name'],
	        ));
		}
    }

	/**
	 * - Add visual feedback that it actually succeeded. Will probably need to modify template.
	 */

}
