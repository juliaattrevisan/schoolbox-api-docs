<?php
/**
 * Remove a question from the Question Bank
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
	$i = 0;

	foreach($_POST['question_bank_id'] as $questionBankId) {
		$questionBankHandler->update(array(
			'question_bank_id' => Scrub::text($questionBankId),
			'deleted_at' => 'NOW()',
		));

		$i++;
	}
}

echo "$i questions removed.";

exit();
