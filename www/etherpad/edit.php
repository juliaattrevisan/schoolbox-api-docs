<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
define('ETHERPAD_API_KEY', 'as90dfuasdifjsdopaifjuhas9p');

/* Check if the currently logged in user can participate
 */
$authenticator = new \Etherpad\Authenticator();

/* The first arg is a composite key, containing groupId and a pad name
 * The actual pad is this composite key
 */
$padId = $this->arg[0];
list($groupId, $padName) = explode('$', $padId);


/* If you can participate, create a session and off you go
 */
if ($authenticator->canEdit($groupId, $_SESSION['user']->getId())) {

	/* Find the author id
	 */
	$client = new \EtherpadLite\Client(ETHERPAD_API_KEY);

	$createResult = $client->createAuthorIfNotExistsFor($_SESSION['user']->getId(), $_SESSION['user']->getFullName());
	$response = $createResult->getResponse();

	/* Spawn a session and redirect to the actual edit url
	 */
	if ($response['message'] == 'ok') {
		$authorId = $response['data']['authorID'];

		$validUntil = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
		$validUntil->addHours(3);

		$sessionResult = $client->createSession($groupId, $authorId, $validUntil->getUnixTimestamp());
		$response = $sessionResult->getResponse();

		if ($response['message'] == 'ok') {
			$sessionId = $response['data']['sessionID'];

			setcookie('sessionID', $sessionId, $_SERVER['REQUEST_TIME'] + 86400, '/');

			$url = "http://192.168.36.10:9001/p/{$padId}";

			header("Location: {$url}");
		}
	}
	else {
		throw new \Exception("Couldn't start session.");
	}
}
else {
	throw new \Exception("You can't edit this pad.");
}