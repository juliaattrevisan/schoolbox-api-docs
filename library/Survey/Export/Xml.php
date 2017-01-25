<?php
/**
 * Survey_Export_Xml
 *
 * Export an entire survey (including sections, questions + solutions) into xml format
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Survey_Export_Xml extends Evaluation_Export_Xml {
	protected $evaluationType = 'survey';
	protected $templateFile = 'survey/exportToMoodle.xml';

	/**
	 * Returns FALSE, reflecting fact that a Survey does not count towards final year mark.
	 *
	 * @return bool
	 */
	public function hasWeighting()
	{
		return FALSE;
	}
}
