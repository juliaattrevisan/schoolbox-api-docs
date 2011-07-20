<?php
/**
 * Assessment creation. Assessments must always belong in a Category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/assess-question.php');
require_once('../library/assess/assess-answer.php');
require_once('../library/assess/assess-questionbank.php');
require_once('../library/group.php');

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

if (!empty($_POST) && is_numeric($_POST['section_id']) && is_array($_POST['question_bank_id']) && count($_POST['question_bank_id'])) {
	$questionBankHandler = new AssessQuestionBank();
	$questionHandler = new AssessQuestion();
	$answerHandler = new AssessAnswer();

	$allQuestionBankIDs = $_POST['question_bank_id'];
	$section_id = scrubTEXT($_POST['section_id']);

	foreach($allQuestionBankIDs as $currentQuestionBankID) {
		$questionBank = $questionBankHandler->getByID(scrubTEXT($currentQuestionBankID));

		$question_id = $questionHandler->create(array(
			'section_id' => $section_id,
			'type_id' => $questionBank['type_id'],
			'sequence' => $questionHandler->getNextSequence($section_id),
			'marks' => $questionBank['marks'],
			'question' => $questionBank['question']
		));

		$answer_id = $answerHandler->create(array(
			'question_id' => $question_id,
			'answer' => $questionBank['answer'],
		));
	}

	exit();
}
else {
	error(2);
}


?>
