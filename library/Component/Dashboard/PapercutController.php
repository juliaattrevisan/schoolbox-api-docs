<?php

/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_PapercutController extends Component_Dashboard_ControllerAbstract {

	/**
	 * Outputs HTML template defined by config variable 'template'
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
	public function renderTemplate($templateHandle) {
		if(!parent::loadTemplate($templateHandle, 'papercut')) {
			return false;
		}

		$this->parseConfig();

		$this->setVar(array(
			'PAPERCUT_USERNAME' => $this->getUser()->getUsername(),
			'PAPERCUT_SERVER' => $this->getConfig('url'),
		));

		return true;
	}
}