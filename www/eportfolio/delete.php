<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$currentUserId = $_SESSION['id'];
/** @var SitePage $this */
$ownerId = $this->arg[0];
$pageId  = $this->arg[1];

if (is_numeric($pageId)) {
    $eportfolioHandler = new Eportfolio($currentUserId, (int)$pageId);
    $authenticator     = new Eportfolio_Authenticator(
        $_SESSION['user'],
        $eportfolioHandler->getPageInstance()->getOwner(),
        $eportfolioHandler->getPageInstance()->getPageId()
    );

    if ($authenticator->canModify()) {
        $eportfolioHandler->delete();
        exit;
    }
}
error(19);
