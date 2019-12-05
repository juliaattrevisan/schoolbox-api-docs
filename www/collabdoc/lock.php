<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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

/* If you are a Creator
 */
if ($authenticator->canCreate($pad->getHomepageId())) {
	if ($pad->isLocked()) {
		$pad->unlock();
	}
	else {
		$pad->lock();
	}

	/* Save pad contents to db, just in case
	 */
	$client = new \EtherpadLite\Client(Config::get('etherpad_api_token'));
	$getTextResult = $client->getHTML($pad->getPadId());

	if ($getTextResult->getMessage() == 'ok') {
		$data = $getTextResult->getData();
		$pad->setBody($data['html']);
	}

	/* Save it!
	 */
	$padMapper->save($pad);

	$this->localRedir("/collabdoc/edit/{$pad->getId()}/{$this->arg[1]}");
}
else {
	error(19);
}
