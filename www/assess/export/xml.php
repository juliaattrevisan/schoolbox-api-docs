<?php

/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$factory         = new Assess_Factory();
$assessHandler   = new Assess_Main();
$sectionHandler  = new Assess_Section();
$groupHandler    = new Group();
$questionHandler = new Assess_Question();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$assess = $assessHandler->getByID($this->arg[0]);


if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(125);
}

/* Export xml
 */
$assessmentMapper = \Schoolbox\Learning\Assessment\DataModel\Mapper::getInstance();
$xml = new Assess_Export_Xml($assess, $factory, $sectionHandler, $questionHandler, $this, $assessmentMapper);
$xml->render();
