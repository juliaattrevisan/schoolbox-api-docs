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

if ($authenticator->canEdit($this->arg[0], $this->arg[1])) {
	echo 'Pad away!';
}
else {
	throw new \Exception("You can't edit this pad.");
}