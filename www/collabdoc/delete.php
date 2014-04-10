<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Load the pad from our db to get its groupId
 * Error out if you can't find it
 */
$padMapper = new \Etherpad\PadMapper();

try {
	$pad = $padMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	exit();
}

/* Check that you have write permissions
 */
$authenticator = new \Etherpad\Authenticator();

if ($authenticator->canCreate($homepageId)) {

	/* Delete it
	 */
	$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));
	$client->deletePad($pad->getPadId());

	/* Soft delete it
	 */
	$pad->setDeletedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
	$padMapper->save($pad);

	$this->localRedir("/homepage/{$pad->getHomepageId()}");
}
else {
	error(19);
}
