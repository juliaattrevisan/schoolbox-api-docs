<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Check that you have write permissions
 */
$authenticator = new \Etherpad\Authenticator();

if ($authenticator->canCreate($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'etherpad/create.html');

	$this->setVar(array(
		'HOMEPAGE_ID' => $this->arg[0]
	));
}
else {
	error(19);
}


