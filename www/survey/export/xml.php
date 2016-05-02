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
$surveyHandler = new Survey_Main();
$groupHandler = new Group();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);

if ($surveyHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < WRITE_ACCESS)) {
	error(125);
}

/* Export xml
 */
$xml = new Survey_Export_Xml($survey, $factory, new Survey_Section(), new Survey_Question(), $this);
$xml->render();
