<?php
/**
 * Assessment Attempt deletion. Responses are all softdeleted if
 * the Attempt is softdeleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

/* Get a list of questions and answers to softdelete
 */
$assessHandler = new Survey_Main();
$attemptHandler = new Survey_Attempt();
$responseHandler = new Survey_Response();
$groupHandler = new Group();

$user_id = $this->arg[1];

/* Check that you can delete these attempts first
 */
$allAttempts = array();

foreach ($_POST['attemptIds'] as $currentAttempt) {
	if (is_numeric($currentAttempt)) {
		$attempt = $attemptHandler->getByID($currentAttempt);

		$attempt['assess'] = $assessHandler->getByID($attempt['assess_id']);

		$allAttempts[] = $attempt;

		if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($attempt['assess']['category_id']) < WRITE_ACCESS)) {
			error(142);
		}
	}
}

/* Multiple soft-delete
 */
foreach ($allAttempts as $currentAttempt) {
	$responseHandler->deleteResponsesForAttempt($currentAttempt['attempt_id']);

	$attemptHandler->update(array(
		'attempt_id' => $currentAttempt['attempt_id'],
		'deleted_at' => 'NOW()'
	));
}

SitePage::backToTheFuture(1);
