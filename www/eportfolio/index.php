<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

/** @var SitePage $this */
$ownerId = is_numeric($this->arg[0]) ? (int)$this->arg[0] : $_SESSION['id'];

try {
    $owner = \Schoolbox\UserMapper::getInstance()->findById($ownerId);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    exit;
}

$page = null;
if (is_numeric($this->arg[1])) {
    try {
        $page = \Eportfolio\PageMapper::getInstance()->findById((int)$this->arg[1]);
        $pageName = $page->getPageId();
    } catch (\Schoolbox\Mapper\Exception $e) {
        $this->pageNotFound();
        exit;
    }
} elseif (in_array($this->arg[1], [\Eportfolio\Page::PROFILE, \Eportfolio\Page::SHOWCASE, \Eportfolio\Page::CREATE])) {
    $pageName = $this->arg[1];
} else {
    $pageName = \Eportfolio\Page::PROFILE;
}

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $page ? $page->getOwner() : $owner, $page ? $page->getPageId() : null);
if ($authenticator->canCreate() || $authenticator->canModify()) {
    $access = UserAccess::ACCESS_FULL;
} elseif ($authenticator->canView()) {
    $access = UserAccess::ACCESS_READ;
} else {
    error(19);
    exit;
}
$eportfolioPage = (new Eportfolio_Page_Factory())->produce($this, $ownerId, $pageName, $access);
null === $eportfolioPage ? $this->pageNotFound() : $eportfolioPage->renderPage();
