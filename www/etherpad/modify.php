<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Check if the currently logged in user can participate
 */
$authenticator = new \Etherpad\Authenticator();

/* Load the pad from our db to get its groupId
 * Error out if you can't find it
 */
$padMapper = new \Etherpad\PadMapper();

try {
	$pad = $padMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(19);
}

/* If you can participate, create a session and off you go
 * Loade le pahde in le olde ifrayme
 */
if ($authenticator->canEdit($pad->getGroupId(), $_SESSION['user']->getId())) {
	$this->setFile(TEMPLATE_content, 'etherpad/modify.html');

	$this->setVar(array(
		'ETHERPAD_URL' => "/etherpad/load/{$pad->getPadId()}"
	));
}
else {
	error(19);
}
