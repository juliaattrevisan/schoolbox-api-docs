<?php
/**
 * Calendar_Api_Config_Connector_Schoolbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Connector_Schoolbox extends Calendar_Api_Config_Connector_Abstract {

	/**
	 * Render configs into admin screen
	 *
	 * @param $page
	 * @return mixed|void
	 */
	public function render($page) {
		$page->setVar(array(
			'CONNECTOR_ID' => $this->getId(),
			'CONNECTOR_CLASS' => $this->getConnectorClass(),
		));

		$page->setVar('has_multiple_options_block', '');

		/* Render event type options
		 */
		$this->renderEventType($page, $this->getLuEventType());
	}
}
