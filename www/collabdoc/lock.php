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
	$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));
	$getTextResult = $client->getText($pad->getPadId());

	if ($getTextResult->getMessage() == 'ok') {
		$data = $getTextResult->getData();
		$pad->setBody($data['text']);
	}

	/* Save it!
	 */
	$padMapper->save($pad);

	$this->localRedir("/collabdoc/modify/{$pad->getId()}");
}
else {
	error(19);
}