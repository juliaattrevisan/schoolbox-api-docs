<?php
/**
 * Component_Config_CommentsAllowed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Config_CommentsAllowed extends Component_Config_Abstract {

	/**
	 * Parse out config options
	 *
	 * @param SitePage $page
	 * @param $key
	 * @param $keyblock
	 * @param $configValue
	 */
	public function parse(SitePage $page, $key, $keyblock, $configValue) {
		if ($configValue == '1') {
			$page->setVar(array(
				'COMPONENT_CONFIG_COMMENTSALLOWED_SELECTED' => 'checked="checked"'
			));
		}
		else {
			$page->setVar(array(
				'COMPONENT_CONFIG_COMMENTSALLOWED_SELECTED' => ''
			));
		}
	}
}