<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper();
$submission = $submissionMapper->findById($this->arg[0]);

$returnMapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
/** @var \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $return */
$return = $returnMapper->findOneByColumn('box_id', $this->arg[0]);

// What's the latest return
$downloader = new Crocodoc\Download($submission, $return);
$downloader->start();

$this->localRedir('/storage/fetch.php?save=1&hash='.$return->getStorage()->getHash());
