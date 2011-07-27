<?php
/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/AssessFactory.php');

$factory = new AssessFactory();
$questionHandler = new AssessQuestion();

$currentQuestion = $questionHandler->getById($this->arg[0]);

if (!$currentQuestion) {
	error(119);
}

/* Set up template
 */
$this->setFile('xml', 'assess/question/exportToMoodle.html');
$this->setBlock('xml', 'answerInputs', 'answerInputs_block');

/* This if() clause allows for sections containing no questions.
 * because they come out with getContents()
 */
if ($currentQuestion['type_id']) {
	$answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

	/* Load answer template into answerOption block
	 */
	$this->setFile('answerFormFragment', 'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/exportToMoodle.html');
	$this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

	$this->setVar(array(
		'QUESTION_QUESTION' => $currentQuestion['question'],
		'QUESTION_MARKS' => $currentQuestion['marks'],
		'QUESTION_TYPE_ID' => $currentQuestion['type_id'],
		'QUESTION_TYPE_MOODLE' => $factory->getMoodleQuestionType($currentQuestion['type_id']),
	));

	/* Load individual answers
	 */
	$answerHandler->exportMoodleAnswer($this, $currentQuestion['question_id']);

	$this->parse('answerInputs_block', 'answerFormFragment', true);
}

/* Create a filename
 */
$filename = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', 'assess_question') . '-' . $currentQuestion['question_id'] . '.xml';

header('Content-type: text/xml');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$this->template->pparse('finaloutput', 'xml');

/* Prevent other template crap from rendering ;)
 */
die();
?>
