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
    $basicListMapper = \Component\Eportfolio\BasicListMapper::getInstance();
    $componentInstanceMapper = Component_InstanceMapper::getInstance();

    /* Add a list item
    */
    if ($action == 'addItem' && $_POST['content']) {
        /* Make sure the component that is being modified is on the page we are modifying */
        $instance = $componentInstanceMapper->findByInstanceId($componentInstanceId, $page->getPageId());
        if (null === $instance) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        $content = $_POST['content'];

        $listItem = new \Component\Eportfolio\BasicList();
        $listItem->setComponentInstanceId($componentInstanceId);
        $listItem->setContent($content);
        $listItem->setSequence(1);

        $basicListMapper->save($listItem);
    } elseif ($action == 'updateItem') {
        /* Update a list item */
        $listItemId = $_POST['listItemId'];
        $content    = $_POST['content'];

        $listItem = $basicListMapper->findById($listItemId);

        /* Make sure the component that is being modified is on the page we are modifying */
        $instance = $componentInstanceMapper->findByInstanceId($listItem->getComponentInstanceId(), $page->getPageId());
        if (null === $instance) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        $listItem->setContent($content);

        $basicListMapper->save($listItem);
    } elseif ($action == 'removeItem') {
        /* Delete a list item */
        $listItemId = $_GET['listItemId'];

        $listItem = $basicListMapper->findById($listItemId);

        /* Make sure the component that is being modified is on the page we are modifying */
        $instance = $componentInstanceMapper->findByInstanceId($listItem->getComponentInstanceId(), $page->getPageId());
        if (null === $instance) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        $basicListMapper->delete($listItem);
    }

    SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}
