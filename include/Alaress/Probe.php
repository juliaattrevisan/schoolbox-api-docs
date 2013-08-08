<?php
/**
 * Alaress_Probe
 *
 * Now outputs to the debugbar if it is enabled!
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Probe {

	/**
	 * Constructor
	 *
	 * @param $dataStructure
	 */
	public function __construct($dataStructure) {
		if (DebugBar\Instance::isEnabled()) {
			DebugBar\Instance::getInstance()->getCollector('messages')->addMessage($dataStructure);
		}
		else {
			echo "<pre>";
			print_r($dataStructure);
			echo "</pre>";
		}
	}
}
