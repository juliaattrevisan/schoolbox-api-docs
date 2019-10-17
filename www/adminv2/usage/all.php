<?php
/**
 * Usage for all users
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

if (!$_SESSION['user']->isSuperuser()) {
	error(19);
}

$mapper = new \Schoolbox\Usage\All\UsageMapper();
$this->setFile(TEMPLATE_content, 'adminv2/usage/all.html');

$this->setVar(array(
	'USAGE_NUM_TOTAL' => $mapper->numUsers()
));

// include the admin navigation
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'usage');
