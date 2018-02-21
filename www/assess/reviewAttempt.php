<?php
/**
 * Bulk assessment attempt review
 */

use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$groupHandler = new Group();

$user_id = $this->arg[1];

$allAttempts = array();

foreach ($_POST['selectedIds'] as $currentAttempt) {
	if (is_numeric($currentAttempt)) {
		$attempt = $attemptHandler->getByID($currentAttempt);

		if (!$assessHandler->isValidUrlAccess($attempt['assess_id'])) {
			$this->pageNotFound();
		}

		/* Author nonexistent? skip 'em
		 */
        try {
            $user = UserMapper::getInstance()->findById($attempt['user_id']);
        }
        catch (Mapper\Exception $e) {
            continue;
        }

        /* Excluded participant? skip 'em
         */
        $actionValidator = new ActionValidator(\Alaress_Date_Format::now(), Access::getInstance());


        if (!$actionValidator->canReviewAttempts($_SESSION['user'], $user, $this->getDi()->get('assessmentDataSource')->findByQuizId($attempt['assess_id'], $user))) {
            $this->pageNotFound();
        }

		$attempt['assess'] = $assessHandler->getByID($attempt['assess_id']);

		$allAttempts[] = $attempt;

		if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($attempt['assess']['category_id']) < WRITE_ACCESS)) {
			error(142);
		}
	}
}

/* Update attempts
 */
$data = [
    'action'  => 'reload',
    'updated' => [],
];
foreach ($allAttempts as $currentAttempt) {

    $data['updated'][$currentAttempt['attempt_id']] = $attemptHandler->update(array(
		'attempt_id' => $currentAttempt['attempt_id'],
		'reviewed_at' => 'NOW()'
	));
}
header('Content-Type: application/json');
die(json_encode($data));

