<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../library/Bootstrap.php';

$page = new SitePage();

$action = $_POST['action'];
$ownerId = $_POST['userId'];
$pageId = 'profile';

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

$message = 'Bad Request';
$code    = 400;
if ($authenticator->canModify()) {
	if ($action == 'addCoverPhoto') {
		$uploadedUid = $_SESSION['id'];
		// Get image out of storage, and save it
		try {
			$factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();
			$imageResource = $factory->build([$_POST['imagehash']])->getFirst();

			$mime = $imageResource->getMimetype();
			if (is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(),'image/')) {
				$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
				$coverPhoto = new \Eportfolio\CoverPhoto();
				$coverPhoto->setUid($ownerId);
				$coverPhoto->setCreatedAt($now);
				$coverPhoto->setUpdatedAt($now);
				$coverPhoto->setImagehash($imageResource->getHash());
				\Eportfolio\CoverPhotoMapper::getInstance()->save($coverPhoto);
				$code    = 200;
				$message = 'OK';
			}
		} catch (\Schoolbox\Resource\Exception $e) {
			$message = 'Internal Server Error';
			$code    = 500;
		}
	}
} else {
	$message = 'Authorization Failed';
	$code    = 401;
}

if ($page->detectAjax()) {
	$page->outputJson(['message' => $message,], false, $code);
} else {
	$page->localRedir("/eportfolio/{$ownerId}/profile");
}
