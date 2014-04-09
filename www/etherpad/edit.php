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

/* Number of hours for which sessions and cookies are valid
 */
$duration = 1;

/* If you can participate, create a session and off you go
 */
if ($authenticator->canEdit($groupId, $_SESSION['user']->getId())) {

	/* Find the author id
	 */
	$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));

	$createResult = $client->createAuthorIfNotExistsFor($_SESSION['user']->getId(), $_SESSION['user']->getFullName());
	$response = $createResult->getResponse();

	/* Spawn a session and redirect to the actual edit url
	 */
	if ($response['message'] == 'ok') {
		$authorId = $response['data']['authorID'];

		$validUntil = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
		$validUntil->addHours($duration);

		$sessionResult = $client->createSession($groupId, $authorId, $validUntil->getUnixTimestamp());
		$response = $sessionResult->getResponse();

		if ($response['message'] == 'ok') {
			$sessionId = $response['data']['sessionID'];

			setcookie('sessionID', $sessionId, $_SERVER['REQUEST_TIME'] + 60*60*$duration, '/');

			/* Remember last updated time
			 */
			$padMapper = new \Etherpad\PadMapper();
			$pad = $padMapper->findByColumn('pad_id', $padId)->first();
			$pad->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			$padMapper->save($pad);

			$this->localRedir(":9001/p/{$padId}");
		}
	}
	else {
		throw new \Exception("Couldn't start session.");
	}
}
else {
	throw new \Exception("You can't edit this pad.");
}