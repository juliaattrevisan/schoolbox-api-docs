<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
$submission = $submissionMapper->findById(18);

$document = new \Crocodoc\Document($submission);
$result = $document->upload();

/* TODO: Add error check here, if $result->error exists
 */
if (isset($result->error)) {
	throw new Exception($result->error);
}

if (isset($result->uuid)) {
	$crocodoc = new \Crocodoc\Loader($result->uuid, $_SESSION['user']);
	$crocodoc->render();
}

exit();