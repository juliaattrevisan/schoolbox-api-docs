<?php
/**
 * Assess_Export_Xml
 *
 * Export an entire quiz (including sections, questions + solutions) into xml format
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Assess_Export_Xml extends Evaluation_Export_Xml {
	protected $evaluationType = 'assess';
	protected $templateFile = 'assess/exportToMoodle.html';


	/**
	 * Returns TRUE, reflecting fact that a Quiz can count towards final year mark.
	 * 
	 * @return bool
	 */
	public function hasWeighting()
	{
		return TRUE;
	}
}
