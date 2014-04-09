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

/* The first arg is a composite key, containing groupId and a pad name
 * The actual pad is this composite key
 */
$padId = $this->arg[0];
list($groupId, $padName) = explode('$', $padId);

/* If you can participate, create a session and off you go
 * Loade le pahde in le olde ifrayme
 */
if ($authenticator->canEdit($groupId, $_SESSION['user']->getId())) {
	$this->setFile(TEMPLATE_content, 'etherpad/modify.html');

	$this->setVar(array(
		'ETHERPAD_URL' => "/etherpad/load/{$padId}"
	));
}
else {
	error(19);
}
