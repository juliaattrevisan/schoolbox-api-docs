<?php
/**
 * Usage for a specific user
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

if (!$_SESSION['user']->isSuperuser()) {
	error(19);
}

if (isset($this->arg[0]) && is_numeric($this->arg[0])) {
	$userMapper = new \Schoolbox\UserMapper();
	$user = $userMapper->findByIdWithFallback($this->arg[0]);
}
else {
	$user = $_SESSION['user'];
}

$mapper = new \Schoolbox\Usage\Specific\UsageMapper($user->getId());
$this->setFile(TEMPLATE_content, 'adminv2/usage/specific.html');

$this->setVar(array(
	'USAGE_USER_FULLNAME' => $user->getFullname(),
	'USAGE_USER_ID' => $user->getId(),
	'USAGE_NUM_TOTAL' => $mapper->numFiles()
));

// include the admin navigation
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'usage');
