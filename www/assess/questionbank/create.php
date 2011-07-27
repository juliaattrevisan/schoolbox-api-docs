<?php
/**
 * Add a question to the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/AssessQuestionBank.php');

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$questionBankHandler = new AssessQuestionBank();

if (!empty($_POST)) {
	$questionBankHandler->addQuestion(
		scrubTEXT($_POST['question_category']),
		scrubTEXT($_POST['type_id']),
		scrubTEXT($_POST['question_id']),
		scrubTEXT($_POST['answer_id']),
		scrubTEXT($_POST['marks'])
	);

	localRedir('/assess/questionbank/created/' . scrubTEXT($_POST['question_id']));
}
else {
	error(2);
}


?>
