<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

// require SitePage context
/** @var SitePage $this */
if (!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    httpError(400, 101); // resource not available
}

/** @var SitePage $this */
if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, '', 403);
}


$this->setLite(true);

$context = $this->arg[0];
$groupId = $this->arg[1];

if (empty($context) || empty($groupId)) {
    httpError(400, 2); // info missing
}

try {
    $factoryFactory   = new Component_FactoryFactory();
    $componentFactory = $factoryFactory->build($context, $this->getDi()->get('session')->get('user'), $this, true);
} catch (Exception $e) {
    httpError(400, 'Invalid context specified.');
    exit;
}
$componentCollection = $componentFactory->buildComponentList($groupId);

$renderer = new Component_Renderer();
$renderer->renderAllAdminComponents($this, $componentCollection);
