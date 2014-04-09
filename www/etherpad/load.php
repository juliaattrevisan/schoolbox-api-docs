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
	exit();
}

/* If you can participate, create a session and off you go
 */
if ($authenticator->canEdit($pad->getGroupId(), $_SESSION['user']->getId())) {

	/* Find the author id
	 */
	$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));

	$createResult = $client->createAuthorIfNotExistsFor($_SESSION['user']->getId(), $_SESSION['user']->getFullName());
	$response = $createResult->getResponse();

	/* Spawn a session and redirect to the actual edit url
	 */
	if ($response['message'] == 'ok') {
		$etherpadSession = new \Etherpad\Session();

		$sessionResult = $client->createSession($pad->getGroupId(), $response['data']['authorID'], $etherpadSession->generateValidUntil()->getUnixTimestamp());
		$response = $sessionResult->getResponse();

		if ($response['message'] == 'ok') {
			$etherpadSession->generateCookie($response['data']['sessionID']);

			/* Remember last updated time
			 */
			$padMapper = new \Etherpad\PadMapper();
			$pad = $padMapper->findByColumn('pad_id', $pad->getPadId())->first();
			$pad->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			$padMapper->save($pad);

			$this->localRedir(":9001/p/{$pad->getPadId()}");
		}
	}
	else {
		throw new \Exception("Couldn't start session.");
	}
}
else {
	throw new \Exception("You can't edit this pad.");
}