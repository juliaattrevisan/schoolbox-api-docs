<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

/* All these are accessed via get from /my-files (My Files)
 */
$action  = $_GET['action'];
$ownerId = $_GET['userId'];
$pageId  = $_GET['pageId'];

/* All three vars need to exist before you can continue:
 * $_SESSION['user'], $ownerId, $pageId
 */
foreach ([$_SESSION['user'], $ownerId, $pageId] as $val) {
    if (null === $val) {
        $page->pageNotFound();
    }
}

try {
    $owner = \Schoolbox\UserMapper::getInstance()->findById($ownerId);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    exit;
}

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $owner, $pageId);

if ($authenticator->canModify()) {
    /* Add a list item
     */
    if ($action == 'addItem') {
        $fileId = $_GET['fileId'];

        /* Prevent dupe publishings
         */
        $existing = \Component\Eportfolio\FileListMapper::getInstance()->findByColumnList(
            [
                'page_id' => $pageId,
                'file_id' => $fileId,
            ]
        );

        /* Publish it if it isn't already published
         */
        if (!$existing->count()) {
            $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

            $listItem = new \Component\Eportfolio\FileList();
            $listItem->setPageId($pageId);
            $listItem->setFileId($fileId);
            $listItem->setCreatedAt($now);
            $listItem->setUpdatedAt($now);

            \Component\Eportfolio\FileListMapper::getInstance()->save($listItem);
        }
    } elseif ($action == 'removeItem') {
        /* Delete a list item */
        $listItemId = $_GET['listItemId'];

        $listItem = \Component\Eportfolio\FileListMapper::getInstance()->findById($listItemId);
        \Component\Eportfolio\FileListMapper::getInstance()->delete($listItem);
    }

    SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}
