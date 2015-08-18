<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// sitepage context is not allowed
use Schoolbox\Assessment\StudentReportCard\Comment;
use Schoolbox\Assessment\StudentReportCard\CommentMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

if(isset($this)) {
	/** @var SitePage $this */
	$this->localRedir('/');
	exit;
}

require_once('../../include/Bootstrap.php');

// do sessiony things
new SitePage();

/* Create a new comment
 */
if (!isset($_REQUEST['commentId']) || !is_numeric($_REQUEST['commentId'])) {
	$auth = new Authenticator();
	$sessionUser = $auth->getSessionUser();

	$commenterId = $sessionUser->getId();

	$categoryId = $_POST['categoryId'];
	$studentId = $_POST['studentId'];

	$body = $_POST['commentBody'];

	$semester = $_POST['semester'];
	$year = $_POST['year'];

	if (!strlen(trim($body))) {
		respond('400 Bad Request', 'Comment cannot be blank.');
	}

	$forceStudentVisible = $sessionUser->getRole()->getType() == Role::STUDENT;
	$forceParentVisible  = $sessionUser->getRole()->getType() == Role::PARENT;

	if(isset($_POST['studentVisibility']) || isset($_POST['parentVisibility'])) {
		$visibility = array(
			'student' => !!$_POST['studentVisibility'] || $forceStudentVisible,
			'parent' => !!$_POST['parentVisibility'] || $forceParentVisible
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
				respond('400 Bad Request', 'You do not have permission to view this category.');
			}
			break;
		case Role::STAFF:
			if(!$gHandler->involvedInSubject($comment->getCategory()->getId())) {
				respond('400 Bad Request', 'You do not have permission to view this category.');
			}
			break;
		case Role::STUDENT:
			if($comment->getCommenter()->getId() != $comment->getStudent()->getId()) {
				respond('400 Bad Request', 'You do not have permission to view this category.');
			}
			break;
	}

	/** @var $commentMapper CommentMapper */
	$commentMapper = CommentMapper::getInstance();
	if(!$commentMapper->save($comment)) {
		respond('400 Bad Request', 'You do not have permission to view this category.');
	}
}

// we are expecting one of three scenarios, all require you to be the owner:
// 1. get comment as it exists
// 2. update comment
// 3. delete comment
// permissions check regardless of method
else if(isset($_REQUEST['commentId']) && is_numeric($_REQUEST['commentId'])) {
	$a = new Authenticator();
	$sessionUser = $a->getSessionUser();

	try {
		/** @var $comment Comment */
		$comment = checkOwnership($sessionUser->getId(), $_REQUEST['commentId']);

	}
	catch(\Schoolbox\Mapper\Exception $e) {
		respond('400 Bad Request', $e->getMessage());
	}

	if(isset($_POST['commentId'])) {
		// expecting an updated version of the comment or deletion request

		/** @var $cMap CommentMapper */
		$cMap = CommentMapper::getInstance();

		if(isset($_POST['deleteComment']) && $_POST['deleteComment'] == true) {
			if(isset($comment)) {
				if($cMap->softDelete($comment)) {
					respond('204 No Content', '');
				}
				else {
					respond('410 Gone', 'Comment has already been deleted.');
				}
			}
		}

		if(isset($_POST['commentBody']) && strlen($_POST['commentBody'])) {
			// update the comment body and set the modified timestamp
			$comment->setBody($_POST['commentBody']);
			$comment->setModified(new Alaress_Date_Format(time(), 'U'));
		}
		else {
			respond('400 Bad Request', 'Comment cannot be blank.');
		}

		if(isset($_POST['studentVisibility']) && is_numeric($_POST['studentVisibility'])) {
			$comment->setStudentVisibility(!!$_POST['studentVisibility']);
		}
		else {
			respond('400 Bad Request', 'Invalid student visibility.');
		}

		if(isset($_POST['parentVisibility']) && is_numeric($_POST['parentVisibility'])) {
			$comment->setParentVisibility(!!$_POST['parentVisibility']);
		}
		else {
			respond('400 Bad Request', 'Invalid parent visibility.');
		}

		if($cMap->save($comment)) {
			// success
			respond('204 No Content', null);

		}
		respond('409 Conflict', 'Something went wrong updating the comment.');

	}
	elseif(isset($_GET['commentId'])) {
		// request for comment as it exists in the db
		if(isset($comment)) {
			header('Content-Type: application/json');
			die(json_encode(array(
				'id' => $comment->getId(),
				'body' => $comment->getBody(),
				'visibility' => array(
					'student' => $comment->getStudentVisibility(),
					'parent' => $comment->getParentVisibility()
				)
			)));
		}
		else {
			// this should be theoretically impossible, but account for it regardless
			respond('400 Bad Request', "No comment found.");
		}
	}
	else {
		// also theoretically impossible
		respond('400 Bad Request', "No comment specified.");
	}
}

/**
 * Checks whether or not the user is the owner of the specified comment
 *
 * @param int $userId
 * @param int $commentId
 * @throws Schoolbox\Mapper\Exception
 * @return bool
 */
function checkOwnership($userId, $commentId) {
	/** @var $cMap CommentMapper */
	$cMap = CommentMapper::getInstance();
	/** @var $comment Comment */
	$comment = $cMap->findById($commentId);


	if($comment->getCommenter()->getId() == $userId) {
		return $comment;
	}
	throw new \Schoolbox\Mapper\Exception("You are not the owner of that comment.");
}

/**
 * Die by sending a plaintext error
 *
 * @param $responseCode
 * @param $body
 */
function respond($responseCode, $body) {
	header("HTTP/1.1 $responseCode");
	header('Content-Type: text/plain');
	die($body);
}