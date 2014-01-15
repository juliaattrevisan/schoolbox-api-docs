<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();

$userMapper = new \Schoolbox\UserMapper();
$user = $userMapper->findById($_POST['uid']);

$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
$submissionBox = $submissionBoxMapper->findById($_POST['box_id']);

$mark = $_POST['mark'];
$comment = $_POST['comment'];
$redirect = $_POST['redirect'];

$file = $_FILES;

$returnedWorkFactory = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\Factory($user, $submissionBox, $mark, $comment, $file, $redirect);
$returnedWorkFactory->start();