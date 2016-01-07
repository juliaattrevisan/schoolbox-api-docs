<?php
/**
 * Bulk assessment attempt review
 */

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$groupHandler = new Group();

$user_id = $this->arg[1];

$allAttempts = array();

foreach ($_POST['selectedIds'] as $currentAttempt) {
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
		'reviewed_at' => 'NOW()'
	));
}