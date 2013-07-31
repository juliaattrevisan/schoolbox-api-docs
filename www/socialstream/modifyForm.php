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

/* Create new SocialStream Post
 */
$factory = new Post\Factory($_POST['type']);

$newPost = $factory->produce();
$newPost->setRootId($_POST['rootId']);
$newPost->setParentId($_POST['parentId']);
$newPost->setHomepageId($_POST['homepageId']);
$newPost->setOwnerId($_SESSION['id']);
$newPost->setUrl($_POST['url']);
$newPost->setBody($_POST['body']);
$newPost->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setDeletedAt(null);

if (strlen(trim($newPost->getBody()))) {

	/* Find thumbnail from the given url in one of the many
	 * ways that they can exist
	 */
	if (strlen($newPost->getUrl())) {
		$chain = new Post\Thumbnail\Chain($newPost);

		/* To prevent a double-curl, remember the place where a thumbnail was
		 * located (this is used as the cached image transform filename hash)
		 */
		if (strlen($chain->getResult())) {
			$newPost->setThumbnail($chain->getResult());

			/* 	Save the original in the cache location
			 */
			$originalThumbnailCache = new Cache\Original();
			$originalThumbnailCache->init($newPost->getThumbnail());
		}
	}

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
else {
	die('Your post must contain something');
}