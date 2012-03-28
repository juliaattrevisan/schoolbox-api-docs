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
require_once('../library/group.php');

/* Get a list of questions and answers to softdelete
 */
$assessHandler = new Survey_Main();
$attemptHandler = new Survey_Attempt();
$responseHandler = new Survey_Response();
$groupHandler = new Group();

$user_id = $this->arg[1];

if (!$groupHandler->isSuperuser()) {
	if (!$assessHandler->isOwner($attempt['assess_id'])) {
		error(126);
	}
}

/* Multiple soft-delete
 */
foreach ($_POST['attemptIds'] as $currentAttempt) {
	if (is_numeric($currentAttempt)) {
		$attempt = $attemptHandler->getByID($currentAttempt);
		$responseHandler->deleteResponsesForAttempt($attempt['attempt_id']);

		$attemptHandler->update(array(
			'attempt_id' => $attempt['attempt_id'],
			'deleted_at' => 'NOW()'
		));
	}
}

SitePage::backToTheFuture(1);
