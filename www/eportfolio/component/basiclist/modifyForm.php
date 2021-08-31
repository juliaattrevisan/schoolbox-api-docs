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

$action              = $_REQUEST['action'];
$ownerId             = $_REQUEST['userId'];
$pageId              = $_REQUEST['pageId'];
$componentInstanceId = $_REQUEST['componentInstanceId'];

/* All three vars need to exist before you can continue:
 * $_SESSION['user'], $ownerId, $pageId
 */
foreach ([$_SESSION['user'], $ownerId, $pageId] as $val) {
    if (null === $val) {
        $page->pageNotFound();
    }
}

try {
    $page = \Eportfolio\PageMapper::getInstance()->findById((int)$pageId);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    exit;
}

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $page->getOwner(), $page->getPageId());

if ($authenticator->canModify()) {
    /* Add a list item
     */
    if ($action == 'addItem' && $_POST['content']) {
        $content = $_POST['content'];

        $listItem = new \Component\Eportfolio\BasicList();
        $listItem->setComponentInstanceId($componentInstanceId);
        $listItem->setContent($content);
        $listItem->setSequence(1);

        \Component\Eportfolio\BasicListMapper::getInstance()->save($listItem);
    } elseif ($action == 'updateItem') {
        /* Update a list item */
        $listItemId = $_POST['listItemId'];
        $content    = $_POST['content'];

        $listItem = \Component\Eportfolio\BasicListMapper::getInstance()->findById($listItemId);
        $listItem->setContent($content);

        \Component\Eportfolio\BasicListMapper::getInstance()->save($listItem);
    } elseif ($action == 'removeItem') {
        /* Delete a list item */
        $listItemId = $_GET['listItemId'];

        $listItem = Component\Eportfolio\BasicListMapper::getInstance()->findById($listItemId);
        Component\Eportfolio\BasicListMapper::getInstance()->delete($listItem);
    }

    SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}
