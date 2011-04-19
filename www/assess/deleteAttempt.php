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
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-attempt.php');
require_once('../library/assess/assess-response.php');
require_once('../library/group.php');

/* Get a list of questions and answers to softdelete
 */
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$responseHandler = new AssessResponse();
$groupHandler = new Group();

$attempt = $attemptHandler->getByID($this->arg[0]);
$user_id = $this->arg[1];

if (!$groupHandler->isSuperuser()) {
	if (!$assessHandler->isOwner($attempt['assess_id'])) {
		error(126);
	}
}

$responseHandler->deleteResponsesForAttempt($attempt['attempt_id']);

$attemptHandler->update(array(
	'attempt_id' => $attempt['attempt_id'],
	'deleted_at' => 'NOW()'
));

SitePage::backToTheFuture(1);
?>