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

/* Create new SocialStream Post
 */
$type = $_POST['type'];
$type = 'website';

$factory = new Post\Factory('website');

$newPost = $factory->produce();
$newPost->setRootId($_POST['rootId']);
$newPost->setParentId($_POST['parentId']);
$newPost->setHomepageId($_POST['homepageId']);
$newPost->setOwnerId($_SESSION['id']);
$newPost->setUrl('http://www.vanessaamorosi.com');
$newPost->setBody($_POST['body']);
$newPost->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setDeletedAt(null);

/* Curl that brutha to find images
 */
if (strlen($newPost->getUrl())) {
	$fileType = new \FileType($newPost->getUrl());
	$extension = $fileType->getExtension();

	/* If it has at least the right extension, download and brownhole it
	 */
	if (in_array($extension, array('jpg', 'gif', 'png'))) {
		$file = new File($newPost->getUrl(), $newPost->getOwnerId());
		$file->saveImage();

		/* Verify that it's actually an allowable image type
		 * before assigning it to the SocialStream post
		 */
		echo 'Streaming image file from the internets: ';
		echo $file->getSavedFile()->getId();

	}
	/* If it's just a url, get it, and find an avatar for it
	 */
	else {
		$curl = new \Alaress_Curl_Url($newPost->getUrl());
		$curl->exec();

		/* Find OG Image
		 */
		$matches = array();

		$pattern = '#property=[\"\']?og:image[\"\']?.*content=[\"\']([^\"\']+)[\"\']?#m';

		preg_match($pattern, $curl->getResult(), $matches);

		/* If an opengraph image was found, curl that too and brownhole it
		 */
		if (count($matches)) {
			$file = new File($matches[1], $newPost->getOwnerId());
			$file->saveImage();

			/* Verify that it's actually an allowable image type
			 * before assigning it to the SocialStream post
			 */
			echo 'Acquiring avatar from og:image and saving it: ';
			echo $file->getSavedFile()->getId();

			//$fileType = new \FileType();
		}
	}
}

die();

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
