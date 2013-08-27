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
	$postMapper = new PostMapper();

	$post = $postMapper->findById($_GET['delete']);
	$post->setDeletedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
	$postMapper->save($post);

	var_dump($post);
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