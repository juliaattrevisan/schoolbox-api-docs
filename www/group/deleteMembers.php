<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

$categoryId = $_REQUEST['categoryID'];
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupMapper = GroupMapper::getInstance();
$uaMapper = UserAccessMapper::getInstance();

// Check access

if ($groupHandler->getPermission($categoryId) < UserAccess::ACCESS_FULL) {
    error(19);
}

// Delete the selected members from the group

foreach ($_POST["deleteIds"] as $memberId) {
    $uaMapper->deleteByColumnList(array(
        "sid" => $categoryId,
        "uid"     => $memberId,
        "service" => UserAccess::SERVICE_HOMEPAGE,
    ));
}

// Reload the page

$page->backToTheFuture(1);
