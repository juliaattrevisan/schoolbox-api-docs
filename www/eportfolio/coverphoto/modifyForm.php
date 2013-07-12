<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';

$page = new SitePage();

$action = $_POST['action'];
$ownerId = $_POST['userId'];
$pageId = 'profile';

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {
	if ($action == 'addCoverPhoto') {
		$uploadedFilename = $_FILES['imagehash']['name'];
		$uploadedLocation = $_FILES['imagehash']['tmp_name'];
		$uploadedUid = $_SESSION['id'];

		if (is_readable($uploadedLocation)) {
			$allowedFiles = new \Schoolbox\File\MimetypeMapper();
			$mime = $allowedFiles->getByFilename($_FILES['imagehash']['name']);

			/* Check mime types and save it if it's an image
			 */
			if (is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(),'image/')) {
				$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

				$coverPhoto = new \Eportfolio\CoverPhoto();
				$coverPhoto->setUid($ownerId);
				$coverPhoto->setCreatedAt($now);
				$coverPhoto->setUpdatedAt($now);

				$imageResource = Schoolbox\FileMapper::getInstance()->upload($uploadedFilename, $uploadedLocation, $uploadedUid);
				$coverPhoto->setImagehash($imageResource->getHash());

				\Eportfolio\CoverPhotoMapper::getInstance()->save($coverPhoto);
			}
		}
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/profile");
}
