<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (!$_SESSION['user']->isSuperuser()) {
	error(19);
}

if (isset($this->arg[0]) && is_numeric($this->arg[0])) {
	$userId = $this->arg[0];
}
else {
	$userId = $_SESSION['user']->getId();
}

$mapper = new \Schoolbox\Usage\Specific\UsageMapper($userId);
$this->setFile(TEMPLATE_content, 'adminv2/usage/specific.html');

$this->setVar(array(
	'USAGE_USER_ID' => $userId,
	'USAGE_NUM_TOTAL' => $mapper->numFiles()
));