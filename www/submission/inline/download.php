<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$returnHandler = new SubmissionReturn();

$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
$submission = $submissionMapper->findById($this->arg[0]);

$downloader = new Crocodoc\Download($submission);
$downloader->start();

/* Get out of here
 */
$this->localRedir($_POST['nextUrl']);