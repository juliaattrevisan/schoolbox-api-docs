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

try {
	$etherpadMapper = new \Etherpad\PadMapper();
	$etherpad = $etherpadMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(164);
}

if ($authenticator->canCreate($etherpad->getHomepageId())) {
	$this->setFile(TEMPLATE_content, 'etherpad/modify.html');

	$this->setVar(array(
		'ETHERPAD_PAD_ID' => $etherpad->getId(),
		'ETHERPAD_PAD_NAME' => $etherpad->getName()
	));
}
else {
	error(19);
}


