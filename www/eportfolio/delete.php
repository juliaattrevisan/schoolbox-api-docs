<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$currentUserId = $_SESSION['id'];
/** @var SitePage $this */
$ownerId = $this->arg[0];
$pageId = $this->arg[1];

try {
    $owner = \Schoolbox\UserMapper::getInstance()->findById($ownerId);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    exit;
}

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $owner, $pageId);

if ($authenticator->canModify()) {
    if (is_numeric($pageId)) {
        $eportfolioHandler = new Eportfolio($currentUserId, $pageId);
        $eportfolioHandler->delete();
    } else {
        /* You cannot delete Profile or Showcase pages :>
         */
        error(19);
    }
} else {
    error(19);
}
