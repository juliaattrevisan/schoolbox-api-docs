<?php

use Schoolbox\Resource\GroupMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

$categoryId = $_REQUEST['categoryID'];
$groupHandler = new Group(HOMEPAGE_SERVICE);

$page = new SitePage();
$groupMapper = GroupMapper::getInstance();
$uaMapper = UserAccessMapper::getInstance();

// Check access

if ($groupHandler->getPermission($categoryId) < FULL_ACCESS) {
    error(19);
}

// Delete the selected members from the group

foreach($_POST["deleteIds"] as $memberId) {
    $uaMapper->deleteByColumnList(array(
        "sid" => $categoryId,
        "uid"     => $memberId,
        "service" => UserAccess::SERVICE_HOMEPAGE,
    ));
}

// Reload the page

$page->backToTheFuture(1);
