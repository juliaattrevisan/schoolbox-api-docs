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

$page = new SitePage();

$action = $_REQUEST['action'];
$ownerId = $_REQUEST['userId'];
$pageId = $_REQUEST['pageId'];
$componentInstanceId = $_REQUEST['componentInstanceId'];

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $ownerId, $pageId);

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
    }
    /* Update a list item
     */
    elseif ($action == 'updateItem') {
        $listItemId = $_POST['listItemId'];
        $content = $_POST['content'];

        $listItem = \Component\Eportfolio\BasicListMapper::getInstance()->findById($listItemId);
        $listItem->setContent($content);

        \Component\Eportfolio\BasicListMapper::getInstance()->save($listItem);
    }
    /* Delete a list item
     */
    elseif ($action == 'removeItem') {
        $listItemId = $_GET['listItemId'];

        $listItem = Component\Eportfolio\BasicListMapper::getInstance()->findById($listItemId);
        Component\Eportfolio\BasicListMapper::getInstance()->delete($listItem);
    }

    SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}
