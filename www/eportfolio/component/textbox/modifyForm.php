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

$action              = $_POST['action'];
$ownerId             = $_POST['userId'];
$pageId              = $_POST['pageId'];
$componentInstanceId = $_POST['componentInstanceId'];

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
    $content = $_POST['content'];

    $textboxCollection = \Component\Eportfolio\TextboxMapper::getInstance()->findByColumn('component_instance_id', $componentInstanceId);

    /* Update existing textbox
     */
    if ($textboxCollection->count()) {
        $textboxItem = $textboxCollection->first();
    } else {
        /* Create new one */
        $textboxItem = new \Component\Eportfolio\Textbox();
        $textboxItem->setComponentInstanceId($componentInstanceId);
    }
    $textboxItem->setContent($content);
    $textboxItem->setSequence(1);
    \Component\Eportfolio\TextboxMapper::getInstance()->save($textboxItem);

    SitePage::localRedir("/eportfolio/{$page->getOwner()->getId()}/{$page->getPageId()}");
}
