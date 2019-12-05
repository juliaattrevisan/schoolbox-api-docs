<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* Check that you have write permissions
 */
$authenticator = new \Etherpad\Authenticator();

if ($_POST['type'] == 'update') {
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
}
else if ($_POST['type'] == 'delete') {

	/* Load the pad from our db to get its groupId
	 * Error out if you can't find it
	 */
	$padMapper = new \Etherpad\PadMapper();

	try {
		$pad = $padMapper->findById($_POST['padId']);
	}
	catch (\Exception $e) {
		exit();
	}

	if ($authenticator->canCreate($pad->getHomepageId())) {

		/* Delete it
		 */
		$client = new \EtherpadLite\Client(Config::get('etherpad_api_token'));
		$client->deletePad($pad->getPadId());

		/* Soft delete it
		 */
		$pad->setDeletedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
		$padMapper->save($pad);

		$this->localRedir("/homepage/{$_POST['homepageId']}");
	}
	else {
		error(19);
	}
}

