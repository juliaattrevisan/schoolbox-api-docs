<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
$page = new \SitePage();

/* Delete a post
 */
if (is_numeric($_GET['delete'])) {
	$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

	/* Softdelete the post and the entire thread
	 */
	$postMapper = new PostMapper();
	$post = $postMapper->findById($_GET['delete']);
	$post->setDeletedAt($now);
	$postMapper->save($post);

	/* This is for top-level posts
	 */
	if ($post->getRootId() == 0) {
		$allPosts = $postMapper->findByColumn('root_id', $_GET['delete']);
	}
	/* For any threaded stuff that isn't a top-level post
	 * delete by parent_id
	 */
	else {
		$allPosts = $postMapper->findByColumn('parent_id', $_GET['delete']);
	}

	/* Softdelete the entire thread for the specified post
	 */
	foreach ($allPosts as $currentPost) {
		$currentPost->setDeletedAt($now);
		 $postMapper->save($currentPost);
	}
}
/* Otherwise create a post
 */
else {
	if (!strlen(trim($_POST['body']))) {
		die('Your post must contain something');
	}

	/* Compile some misc data
	 */
	$data = array_merge($_POST, array(
		'ownerId' => $_SESSION['id'],
		'requestTime' => new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'),
	));

	/* Create new SocialStream Post
	 */
	$factory = new Post\Factory\Creational($_POST['socialstreamAttachment'], $_POST['url']);
	$newPost = $factory->produce($data);

	/* Set up authentication
	 * Put your permissions and stuff in here, just like ePortfolio v2
	 */
	$instanceMapper = \Component_InstanceMapper::getInstance();

	/* Seriously?
	 */
	$instanceArray = $instanceMapper->findByInstanceId($_POST['instanceId']);
	$instance = $instanceArray[$_POST['instanceId']];

	/* Current User
	 */
	$currentUser = \Schoolbox\UserMapper::getInstance()->findById($_SESSION['id']);

	/* Authenticate!
	 */
	$authenticator = new Authenticator($newPost, $instance, $currentUser);

	/* Find out if you can collaborate on this
	*/
	if ($authenticator->canCollaborate() || $authenticator->canAddComment()) {
		$mapper = new PostMapper();
		$mapper->save($newPost);

		$page->backToTheFuture(1);
	}
	else {
		error(19);
	}
}