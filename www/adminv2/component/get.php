<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
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
