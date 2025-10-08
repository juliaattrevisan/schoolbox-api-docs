<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Error\Mapper as ErrorMapper;

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

try {
    $page = \Eportfolio\PageMapper::getInstance()->findById((int)$pageId);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    exit;
}

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $page->getOwner(), $page->getPageId());

if ($authenticator->canModify()) {
    $componentInstanceMapper = Component_InstanceMapper::getInstance();

    /* Add a list item
     */
    if ($action == 'addItem') {
        /* Make sure the component that is being modified is on the page we are modifying */
        $instance = $componentInstanceMapper->findByInstanceId($componentInstanceId, $page->getPageId());
        if (null === $instance) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        $title = $_POST['title'];
        $content = $_POST['content'];

        /* Check if it's a valid url before putting in db
         */
        $url = new Url($content);

        if (!$url->isLocalUrl()) {
            $url->prependScheme();
        }

        $listItem = new \Component\Eportfolio\LinkList();
        $listItem->setComponentInstanceId($componentInstanceId);
        $listItem->setTitle($title);
        $listItem->setContent($url->getUrl());
        $listItem->setSequence(1);

        \Component\Eportfolio\LinkListMapper::getInstance()->save($listItem);
    } elseif ($action == 'updateItem') {
        /* Update a list item */
        $listItemId = $_POST['listItemId'];
        $title = $_POST['title'];
        $content = $_POST['content'];

        /* Check if it's a valid url before putting in db
         */
        $url = new Url($content);

        if (!$url->isLocalUrl()) {
            $url->prependScheme();
        }

        $listItem = \Component\Eportfolio\LinkListMapper::getInstance()->findById($listItemId);

        /* Make sure the component that is being modified is on the page we are modifying */
        $instance = $componentInstanceMapper->findByInstanceId($listItem->getComponentInstanceId(), $page->getPageId());
        if (null === $instance) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        $listItem->setTitle($title);
        $listItem->setContent($url->getUrl());

        \Component\Eportfolio\LinkListMapper::getInstance()->save($listItem);
    } elseif ($action == 'removeItem') {
        /* Delete a list item */
        $listItemId = $_GET['listItemId'];

        $listItem = \Component\Eportfolio\LinkListMapper::getInstance()->findById($listItemId);

        /* Make sure the component that is being modified is on the page we are modifying */
        $instance = $componentInstanceMapper->findByInstanceId($listItem->getComponentInstanceId(), $page->getPageId());
        if (null === $instance) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        \Component\Eportfolio\LinkListMapper::getInstance()->delete($listItem);
    }

    SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}
