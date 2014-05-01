<?php
/**
 * Allows you to update various configs for a submission box etc.
 * from within inline grading
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

/* Start a session -_-
 */
$page = new SitePage();

/* Check submission box exists
 * Check user exists
 */
$boxId = $_POST['boxId'];
$userId = $_POST['userId'];

try {
	$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
	$submissionBox = $submissionBoxMapper->findById($boxId);
}
catch (\Exception $e) {
	error(17);
}

try {
	$userMapper = new Schoolbox\UserMapper();
	$user = $userMapper->findById($userId);
}
catch (\Exception $e) {
	error(31);
}

/* Check permissions
 * Auth: submission's category must be writable by this user.
 */
$groupHandler = new \Group();

if ($groupHandler->getPermission($submissionBox->getCategory()->getId()) < WRITE_ACCESS) {
	error(19);
}

/* Otherwise continue updating config
 */
$submissionBox->setSkipUnsubmitted($_POST['skipUnsubmitted']);
$submissionBoxMapper->save($submissionBox);

/* TODO: echo json_encoded object for ui feedback
 */

exit();