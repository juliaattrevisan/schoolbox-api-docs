<?php

/*
 *
 * redirect to Redirection to 16.5 url
 *
 */
SitePage::localRedir('/', null, 410);

/*
*Old code
*/
/**
 * Allows you to update various configs for a submission box etc.
 * from within inline grading
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../library/Bootstrap.php');

/* Start a session -_-
 */
$page = new SitePage();

/* Check submission box exists
 * Check user exists
 */
$boxId = $_POST['boxId'];
$skip  = isset($_POST['skipUnsubmitted']) && $_POST['skipUnsubmitted'] && $_POST['skipUnsubmitted'] != 'false' ? 1 : 0; // because it's hydrated as an int, not a boolean -_- // oh, and it might come through as the string "false", because Newspeak, that's why

try {
	$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
	$submissionBox = $submissionBoxMapper->findById($boxId);
}
catch (\Exception $e) {
	error(17);
    exit;
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
$submissionBox->setSkipUnsubmitted($skip);
$submissionBoxMapper->save($submissionBox);

/* TODO: echo json_encoded object for ui feedback
 */

exit();
