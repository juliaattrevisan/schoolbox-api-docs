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

if(isset($this)) {
	/** @var SitePage $this */
	$this->localRedir('/');
	exit;
}

require_once('../../include/Bootstrap.php');

// do sessiony things
new SitePage();


// we are expecting one of three scenarios, all require you to be the owner:
// 1. get comment as it exists
// 2. update comment
// 3. delete comment

// permissions check regardless of method
if(isset($_REQUEST['commentId']) && is_numeric($_REQUEST['commentId'])) {
	$a = new Authenticator();
	$sessionUser = $a->getSessionUser();

	try {
		/** @var $comment Comment */
		$comment = checkOwnership($sessionUser->getId(), $_REQUEST['commentId']);
	}
	catch(\Schoolbox\Mapper\Exception $e) {
		respond('400 Bad Request', $e->getMessage());
	}
}
else {
	respond('400 Bad Request', "No comment specified.");
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
		$comment->setModified(new Alaress_Date_Format(time()));
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