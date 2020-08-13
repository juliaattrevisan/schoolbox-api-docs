<?php
/**
 * Usage for all users
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;

if (!$_SESSION['user']->isSuperuser()) {
    error(19, "", 401);
}

$mapper = new \Schoolbox\Usage\All\UsageMapper();
/** @var \Schoolbox\IConfigLoader $config */
$config = $this->getDi()->get('config');

if ($config->get(ConfigMapper::NAME_OTFS_ADAPTER) === \Schoolbox\Resource\FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3) {
    $this->setFile(TEMPLATE_content, 'adminv2/usage/allAws.html');
} else {
    $this->setFile(TEMPLATE_content, 'adminv2/usage/all.html');
}

$this->setVar([
    'USAGE_NUM_TOTAL' => $mapper->numUsers(),
]);

// include the admin navigation
$nav = new AdminV2_Nav($config);
$nav->parse($this, 'usage');
