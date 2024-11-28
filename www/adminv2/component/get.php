<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// require SitePage context

use Schoolbox\Error\Mapper;
use Schoolbox\Permission\Access\UserAccess;

if (!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    error(Mapper::ERROR_RESOURCE_NOT_FOUND, '', 400);
}

/** @var SitePage $this */

$context = $this->arg[0];
$groupId = $this->arg[1];

if (
    !(
        $_SESSION['user']->isSuperuser() ||
        ($_SESSION['user']->hasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL) && $context === 'homepage') ||
        ($_SESSION['user']->hasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL) && $context === 'dashboard')
    )
) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, '', 403);
}


$this->setLite(true);

if (empty($context) || empty($groupId)) {
    error(Mapper::ERROR_INFORMATION_MISSING, '', 400);
}

try {
    $factoryFactory   = new Component_FactoryFactory();
    $componentFactory = $factoryFactory->build($context, $this->getDi()->get('session')->get('user'), $this, true);
} catch (Exception $e) {
    error(null, 'Invalid context specified', 400);
}
$componentCollection = $componentFactory->buildComponentList($groupId);

$renderer = new Component_Renderer();
$renderer->renderAllAdminComponents($this, $componentCollection);
