<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$currentUserId = $_SESSION['id'];
$ownerId       = is_numeric($this->arg[0]) ? $this->arg[0] : $_SESSION['id'];

$pageId = $this->arg[1] ? $this->arg[1] : 'profile';

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $ownerId, $pageId);

if ($authenticator->pageExists()) {
    if ($authenticator->canCreate() || $authenticator->canModify()) {
        $eportfolioPage = (new Eportfolio_Page_Factory())->produce($this, $ownerId, $pageId, UserAccess::ACCESS_FULL);

        null === $eportfolioPage ? $this->pageNotFound() : $eportfolioPage->renderPage();
    } else if ($authenticator->canView()) {
        $eportfolioPage = (new Eportfolio_Page_Factory())->produce($this, $ownerId, $pageId, UserAccess::ACCESS_READ);

        null === $eportfolioPage ? $this->pageNotFound() : $eportfolioPage->renderPage();
    } else {
        error(19);
    }
} else {
    $this->pageNotFound();
}
