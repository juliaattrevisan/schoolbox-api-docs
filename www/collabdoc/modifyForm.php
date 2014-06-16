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
	$etherpad = $etherpadMapper->findById($_POST['padId']);
}
catch (\Exception $e) {
	error(164);
}

if ($authenticator->canCreate($etherpad->getHomepageId())) {
	$etherpad->setName($_POST['name']);
	$etherpadMapper->save($etherpad);

	$this->backToTheFuture(2);
}
else {
	error(19);
}


