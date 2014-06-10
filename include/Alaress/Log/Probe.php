<?php
/**
 * Alaress_Probe
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Log_Probe {

	/**
	 * Constructor
	 *
	 * @param $dataStructure
	 */
	public function __construct($dataStructure) {
		echo "<pre>";
		print_r($dataStructure);
		echo "</pre>";
	}
}
