<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// no sitepage context allowed
use Schoolbox\Assessment\StudentReportCard\Comment;
use Schoolbox\Assessment\StudentReportCard\CommentMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

if(isset($this)) {
	/** @var SitePage $this */
	$this->localRedir('/submission/allWork');
	exit;
}

require_once('../../include/Bootstrap.php');

// do session-commencing activities
new SitePage();

$auth = new Authenticator();
$sessionUser = $auth->getSessionUser();

$commenterId = $sessionUser->getId();

$categoryId = $_POST['categoryId'];
$studentId = $_POST['studentId'];

$body = $_POST['comment'];

$semester = $_POST['semester'];
$year = $_POST['year'];

$forceStudentVisible = $sessionUser->getRole()->getType() == Role::STUDENT;
$forceParentVisible  = $sessionUser->getRole()->getType() == Role::PARENT;

if(array_key_exists('visibility', $_POST)) {
	$visibility = array(
		'student' => array_key_exists('student', $_POST['visibility']) || $forceStudentVisible,
		'parent' => array_key_exists('parent', $_POST['visibility']) || $forceParentVisible
	);
}
else {
	// if no visibilities checked, default to mandatory options only (you can't hide comments from yourself)
	$visibility = array(
		'student' => $forceStudentVisible,
		'parent'  => $forceParentVisible
	);
}


/**
 * @var $uMap UserMapper
 * @var $cMap CategoryMapper
 */
$uMap = UserMapper::getInstance();
$cMap = CategoryMapper::getInstance();

// set up this comment, ready to go
$comment = new Comment();
$comment->setCommenter($sessionUser);
$comment->setBody($body);
$comment->setStudent($uMap->findById($studentId));
$comment->setCategory($cMap->findById($categoryId));
$comment->setStudentVisibility($visibility['student']);
$comment->setParentVisibility($visibility['parent']);


// PERMISSIONS CHECK!
$gHandler = new Group();

switch($comment->getCommenter()->getRole()->getType()) {
	case Role::PARENT:
		if(!$gHandler->isParentOfChild($comment->getStudent()->getId())) {
			error(14);
		}
		break;
	case Role::STAFF:
		if(!$gHandler->involvedInSubject($comment->getCategory()->getId())) {
			error(14);
		}
		break;
	case Role::STUDENT:
		if($comment->getCommenter()->getId() != $comment->getStudent()->getId()) {
			error(14);
		}
		break;
}

/** @var $commentMapper CommentMapper */
$commentMapper = CommentMapper::getInstance();
if(!$commentMapper->save($comment)) {
	error(14);
}

SitePage::localRedir("/submission/allWork/$studentId/$year/$semester/$categoryId");