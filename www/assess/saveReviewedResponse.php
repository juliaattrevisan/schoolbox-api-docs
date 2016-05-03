<?php
/**
 * Saves a Reviewed Response to a Question. This is where the assessor can
 * change the mark.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Assess_Main();
$questionHandler = new Assess_Question();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();

$question = $questionHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($question['section_id']);
$assess = $assessHandler->getByID($section['assess_id']);

if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
    $this->pageNotFound();
}

if ($assessHandler->isDeleted($section['assess_id'])) {
	error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(127);
}

if (!$question) {
    error(119);
}

$factory = new Assess_Factory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$result = $answerHandler->saveReviewedResponse($_POST, $this->arg[1]);

echo json_encode($result);

/* Prevent rendering of template
 */
exit();

