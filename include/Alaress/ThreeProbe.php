<?php
/**
 * ThreeProbe
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\DebugBar\Instance as DebugBar;

class Alaress_ThreeProbe {

	/**
	 * Constructor
	 *
	 * @param $dataStructure1
	 * @param $dataStructure2
	 * @param $dataStructure3
	 * @internal param $dataStructure
	 */
	public function __construct($dataStructure1, $dataStructure2, $dataStructure3) {
		if (DebugBar::isEnabled()) {
		}
		else {
			echo "<div style='width:33%;float:left;display:block;overflow:hidden;'>";
			echo "<pre>";
			print_r($dataStructure1);
			echo "</pre>";
			echo "</div>";

			echo "<div style='width:33%;float:left;display:block;overflow:hidden;'>";
			echo "<pre>";
			print_r($dataStructure2);
			echo "</pre>";
			echo "</div>";

			echo "<div style='width:33%;float:left;display:block;overflow:hidden;'>";
			echo "<pre>";
			print_r($dataStructure3);
			echo "</pre>";
			echo "</div>";
		}
	}
} 