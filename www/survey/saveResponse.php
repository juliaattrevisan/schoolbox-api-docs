<?php
/**
 * Saves a Response to a Question.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Survey_Main();
$questionHandler = new Survey_Question();
$sectionHandler = new Survey_Section();

$question = $questionHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($question['section_id']);

/* TODO: Check if this test is for your category
 */

/* Disabled: authors can try their own surveys
if ($assessHandler->isOwner($section['assess_id'])) {
    error(124);
}
*/

if ($assessHandler->isExpired($section['assess_id'])) {
    error(120);
}

if ($assessHandler->isDeleted($section['assess_id'])) {
	error(112);
}


if (!$question) {
    error(119);
}

$factory = new Survey_Factory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$data = $_POST;
$data['section_id'] = $question['section_id'];
$data['question_id'] = $this->arg[0];

$result = $answerHandler->saveResponse($data);

/* Find out how many questions were completed in this section already
 */

$result['percentageComplete'] = $sectionHandler->sectionPercentageComplete($question['section_id']);

echo json_encode($result);

/* Prevent rendering of template
 */
exit();
