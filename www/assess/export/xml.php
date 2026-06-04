<?php

/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* In learnosity era, we don't have an import/export mechanism.
 * We'd have to build it from the ground up.
 *
 * IF YOU REVIVE EXPORT: as of SA-22007 mark_visibility is stored and emitted as a string
 * (e.g. "staffStudentAndParent"), no longer an integer. The export template
 * (templates/core/assess/exportToMoodle.xml) still declares <version>17.0</version>, whose
 * import parser (library/Assess/Import/Parser/V160.php) casts markvisibility to int. A revived
 * exporter MUST declare a new format version and ship a matching parser that reads the string
 * value, otherwise string visibilities will silently become MARK_VISIBILITY_PRIVATE on import.
 */
$this->pageNotFound();

//use Schoolbox\Permission\Access\UserAccess;
//
//$factory         = new Assess_Factory();
//$assessHandler   = new Assess_Main();
//$sectionHandler  = new Assess_Section();
//$groupHandler    = new Group();
//$questionHandler = new Assess_Question();
//
//if (!is_numeric($this->arg[0])) {
//    error(112);
//}
//
//if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
//    $this->pageNotFound();
//}
//
//$assess = $assessHandler->getByID($this->arg[0]);
//
//
//if ($assessHandler->isDeleted($this->arg[0])) {
//    error(112);
//}
//
//if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
//    error(125);
//}
//
///* Export xml
// */
//$assessmentMapper = \Schoolbox\Learning\Assessment\DataModel\Mapper::getInstance();
//$xml = new Assess_Export_Xml($assess, $factory, $sectionHandler, $questionHandler, $this, $assessmentMapper);
//$xml->render();
