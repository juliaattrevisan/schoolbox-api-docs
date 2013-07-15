<?php
/**
 * Component_Eportfolio_List
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_SocialStreamController extends Component_Homepage_ControllerAbstract {

	/**
	 * Prepares data for the render function. Business logic.
	 *
	 * @return void
	 */
	public function execute() {
		return true;
	}

	/**
	 * Outputs HTML template defined by config variable 'template'
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
	public function renderTemplate($templateHandle) {
		if (!parent::loadTemplate($templateHandle, 'socialstream')) {
			return false;
		}

		$this->parseConfig();

		return true;
	}
}