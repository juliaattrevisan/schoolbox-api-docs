<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$action = $_POST['action'];
$ownerId = $_POST['userId'];
$pageId = 'profile';

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $ownerId, $pageId);

$message = 'Bad Request';
$hash    = null;
$code    = 400;
if ($authenticator->canModify()) {
    if ($action == 'addCoverPhoto') {
        $uploadedUid = $_SESSION['id'];
        // Get image out of storage, and save it
        try {
            $factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();
            $imageResource = $factory->build([$_POST['imagehash']])->getFirst();

            $mime = $imageResource->getMimetype();
            if (is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(), 'image/')) {
                $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
                $coverPhoto = new \Eportfolio\CoverPhoto();
                $coverPhoto->setUid($ownerId);
                $coverPhoto->setCreatedAt($now);
                $coverPhoto->setUpdatedAt($now);
                $coverPhoto->setImagehash($imageResource->getHash());
                \Eportfolio\CoverPhotoMapper::getInstance()->save($coverPhoto);
                $code    = 200;
                $message = 'OK';
                $hash    = $coverPhoto->getImagehash();
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
    $page->outputJson(['message' => $message, 'hash' => $hash], false, $code);
} else {
    $page->localRedir("/eportfolio/{$ownerId}/profile");
}
