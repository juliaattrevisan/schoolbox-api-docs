<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$action = $_REQUEST['action'];
$ownerId = $_REQUEST['userId'];
$pageId = $_REQUEST['pageId'];
$componentInstanceId = $_REQUEST['componentInstanceId'];

/* All three vars need to exist before you can continue:
 * $_SESSION['user'], $ownerId, $pageId
 */
foreach ([$_SESSION['user'], $ownerId, $pageId] as $val) {
    if (null === $val) {
        $page->pageNotFound();
    }
}

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $ownerId, $pageId);

if ($authenticator->canModify()) {
    /* Add a list item
     */
    if ($action == 'addItem' && $_POST['title']) {
        $title = $_POST['title'];
        $content = $_POST['content'];

        $listItem = new \Component\Eportfolio\AdvancedList();
        $listItem->setComponentInstanceId($componentInstanceId);
        $listItem->setTitle($title);
        $listItem->setContent($content);
        $listItem->setSequence(1);

        $uploadedFilename = $_FILES['imagehash']['name'];
        $uploadedLocation = $_FILES['imagehash']['tmp_name'];
        $uploadedUid = $_SESSION['id'];

        if (is_readable($uploadedLocation)) {
            $allowedFiles = new \Schoolbox\File\MimetypeMapper();
            $mime = $allowedFiles->getByFilename($_FILES['imagehash']['name']);

            if (is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(), 'image/')) {
                try {
                    $imageResource = Schoolbox\Resource\Storage\Mapper::getInstance()->accept($uploadedFilename, $uploadedLocation, $uploadedUid);
                    $listItem->setImagehash($imageResource->getHash());
                } catch (Exception $e) {
                    //Silently fail if image cannot be uploaded
                }
            }
        }

        \Component\Eportfolio\AdvancedListMapper::getInstance()->save($listItem);
    }
    /* Update a list item
     */
    elseif ($action == 'updateItem' && $_POST['title']) {
        $listItemId = $_POST['listItemId'];
        $title = $_POST['title'];
        $content = $_POST['content'];

        $listItem = \Component\Eportfolio\AdvancedListMapper::getInstance()->findById($listItemId);
        $listItem->setTitle($title);
        $listItem->setContent($content);

        $uploadedFilename = $_FILES['imagehash']['name'];
        $uploadedLocation = $_FILES['imagehash']['tmp_name'];
        $uploadedUid = $_SESSION['id'];

        if (is_readable($uploadedLocation)) {
            $allowedFiles = new \Schoolbox\File\MimetypeMapper();
            $mime = $allowedFiles->getByFilename($_FILES['imagehash']['name']);

            if (is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(), 'image/')) {
                $imageResource = Schoolbox\Resource\Storage\Mapper::getInstance()->accept($uploadedFilename, $uploadedLocation, $uploadedUid);
                $listItem->setImagehash($imageResource->getHash());
            }
        }

        \Component\Eportfolio\AdvancedListMapper::getInstance()->save($listItem);
    }
    /* Delete a list item
     */
    elseif ($action == 'removeItem') {
        $listItemId = $_GET['listItemId'];

        $listItem = Component\Eportfolio\AdvancedListMapper::getInstance()->findById($listItemId);
        Component\Eportfolio\AdvancedListMapper::getInstance()->delete($listItem);
    }

    SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}
