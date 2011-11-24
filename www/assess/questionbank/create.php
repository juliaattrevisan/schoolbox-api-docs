<?php
/**
 * Add a question to the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$questionBankHandler = new Assess_Question_Bank();

if (!empty($_POST)) {
	$questionBankHandler->addQuestion(
		Scrub::text($_POST['question_category']),
		Scrub::text($_POST['type_id']),
		Scrub::text($_POST['question_id']),
		Scrub::text($_POST['answer_id']),
		Scrub::text($_POST['marks'])
	);

	SitePage::localRedir('/assess/questionbank/created/' . Scrub::text($_POST['question_id']));
}
else {
	error(2);
}