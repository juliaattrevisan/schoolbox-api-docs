<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../../include/Bootstrap.php';

$page = new SitePage();

$action = $_POST['action'];
$ownerId = $_POST['userId'];
$pageId = $_POST['pageId'];
$listItemId = $_POST['listItemId'];
$content = $_POST['content'];

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canView()) {

	/* Add a comment to pages you can view
	 */
	if ($action == 'addComment') {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
		$authorId = $_SESSION['id'];

		$blogComment = new \Component\Eportfolio\BlogComment();
		$blogComment->setBlogId($listItemId);
		$blogComment->setUid($authorId);
		$blogComment->setContent($content);
		$blogComment->setCreatedAt($now->getDatabase());
		$blogComment->setUpdatedAt($now->getDatabase());

		\Component\Eportfolio\BlogCommentMapper::getInstance()->save($blogComment);
	}
//	/* Update a list item
//	 */
//	else if ($action == 'updateItem') {
//		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
//
//		$listItem = \Component\Eportfolio\BlogMapper::getInstance()->findById($listItemId);
//		$listItem->setTitle($title);
//		$listItem->setContent($content);
//		$listItem->setUpdatedAt($now->getDatabase());
//
//		\Component\Eportfolio\BlogMapper::getInstance()->save($listItem);
//	}
	/* Delete a list item
	 */
	else if ($action == 'removeComment') {
		$commentId = $_POST['commentId'];

		$listItem = \Component\Eportfolio\BlogCommentMapper::getInstance()->findById($commentId);

		/* Blog authors can delete anyone's comment
		 * Comment authors are allowed to delete their own comments, but a ui is not provided yet.
		 */
		if (($listItem->getUid() == $_SESSION['id']) || ($authenticator->canModify())) {
			\Component\Eportfolio\BlogCommentMapper::getInstance()->delete($listItem);
		}
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}
