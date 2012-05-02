<?php
/**
 * Survey export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$factory = new Survey_Factory();
$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$survey = $assessHandler->getByID($this->arg[0]);

if (!$survey) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

/* Export xml
 */
$xml = new Survey_Export_Xml($survey, $factory, $sectionHandler, $this);
$xml->render();
