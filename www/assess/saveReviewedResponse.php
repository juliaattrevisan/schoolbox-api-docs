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
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-section.php');

$assessHandler = new Assess();
$questionHandler = new AssessQuestion();
$sectionHandler = new AssessSection();

$question = $questionHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($question['section_id']);

if (!$assessHandler->isOwner($section['assess_id'])) {
    error(127);
}

if (!$question) {
    error(119);
}

$factory = new AssessFactory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$result = $answerHandler->saveReviewedResponse($_POST, $this->arg[1]);

echo json_encode($result);

/* Prevent rendering of template
 */
exit();

?>