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
class Survey_Export_Xml extends Evaluation_Export_Xml
{
	/**
	 * Returns FALSE, reflecting fact that a Survey does not count towards final year mark.
	 *
	 * @return bool
	 */
    protected function hasWeighting()
	{
		return FALSE;
	}

    protected function getEvaluationType()
    {
        return 'survey';
    }

    protected function getTemplateFile()
    {
        return 'survey/exportToMoodle.xml';
    }
}
