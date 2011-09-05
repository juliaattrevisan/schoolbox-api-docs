<?php
/**
 * Remove a question from the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/survey/SurveyQuestion.php');
require_once('../library/survey/SurveyAnswer.php');
require_once('../library/survey/SurveyQuestionBank.php');

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$questionBankHandler = new SurveyQuestionBank();

if (!empty($_POST)) {
	$i = 0;

	foreach($_POST['delete_question_bank_id'] as $questionBankId) {
		$questionBankHandler->update(array(
			'question_bank_id' => Scrub::text($questionBankId),
			'deleted_at' => 'NOW()',
		));

		$i++;
	}
}

echo "$i questions removed.";

exit();
?>
