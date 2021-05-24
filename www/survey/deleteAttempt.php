<?php

/**
 * Survey Attempt deletion. Responses are all softdeleted if
 * the Attempt is softdeleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$surveyHandler   = new Survey_Main();
$attemptHandler  = new Survey_Attempt();
$responseHandler = new Survey_Response();
$groupHandler    = new Group();
$user_id = $this->arg[1];
/* Check that you can delete these attempts first
 */
$allAttempts = array();
foreach ($_POST['attemptIds'] as $currentAttempt) {
    if (is_numeric($currentAttempt)) {
        $attempt = $attemptHandler->getByID($currentAttempt);
        if (!$surveyHandler->isValidUrlAccess($attempt['assess_id'])) {
            $this->pageNotFound();
        }

        $attempt['assess'] = $surveyHandler->getByID($attempt['assess_id']);
        $allAttempts[] = $attempt;
        if (!$surveyHandler->isOwner($attempt['assess_id']) && ($groupHandler->getPermission($attempt['assess']['category_id']) < UserAccess::ACCESS_WRITE)) {
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
