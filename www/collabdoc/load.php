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
	exit();
}

/* If you can participate, create a session and off you go
 */
if ($authenticator->isParticipator($pad->getHomepageId())) {

	/* Check whether this pad record exists from a different environment
	 * e.g. copied pads from live environment to dev environment
	 *
	 * Update the environment value so it's in the current environment
	 * then continue on as normal
	 */
	if (!$pad->isExistsInCurrentEnvironment()) {
		$pad->setEnvironment(INSTANCE_ID);
		$padMapper->save($pad);

		$respawn = new \Etherpad\Spawnpoint($this);
		$pad = $respawn->respawn($pad);
	}

	/* Find the author id
	 */
	$client = new \EtherpadLite\Client(Config::get('etherpad_api_token'));

	$createResult = $client->createAuthorIfNotExistsFor($_SESSION['user']->getId(), $_SESSION['user']->getFullName());
	$response = $createResult->getResponse();

	/* Spawn a session and redirect to the actual edit url
	 */
	if ($createResult->getMessage() == 'ok') {
		$etherpadSession = new \Etherpad\Session();

		list($groupId, $padName) = explode('$', $pad->getPadId());

		$sessionResult = $client->createSession($groupId, $response['data']['authorID'], $etherpadSession->generateValidUntil()->getUnixTimeStamp());
		$data = $sessionResult->getData();

		/* When the groupId is nonexistent in Etherpad
		 *
		 * That happens when we have the pad in our local db but someone purges
		 * epl's memory or redis. Reincarnate the pad in epl/redis and spawn a
		 * new attempt at getting the sessionResult
		 */
		if ($sessionResult->getMessage() != 'ok') {
			$respawn = new \Etherpad\Spawnpoint($this);
			$pad = $respawn->respawn($pad);

			list($groupId, $padName) = explode('$', $pad->getPadId());

			$sessionResult = $client->createSession($groupId, $response['data']['authorID'], $etherpadSession->generateValidUntil()->getUnixTimeStamp());
			$data = $sessionResult->getData();
		}

		/* Otherwise continue as normal
		 */
		if ($sessionResult->getMessage() == 'ok') {
			$etherpadSession->generateCookie($data['sessionID']);

			/* Remember last updated time
			 */
			$pad = $padMapper->findByColumn('pad_id', $pad->getPadId())->first();
			$pad->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			$padMapper->save($pad);

			if ($authenticator->canCreate($pad->getHomepageId()) || !$pad->isLocked()) {
				$this->localRedir(':' . $this->getWebsocketPort() . "/pad/p/{$pad->getPadId()}");
			}
			else if ($pad->isLocked()) {
				$readonlyResult = $client->getReadOnlyID($pad->getPadId());
				$data = $readonlyResult->getData();

				if ($readonlyResult->getMessage() == 'ok') {
					$this->localRedir(":" . $this->getWebsocketPort() . "/pad/p/{$data['readOnlyID']}?showControls=false");
				}
			}
		}
		else {
			throw new \Exception("Couldn't start session.");
		}
	}
	else {
		throw new \Exception("Couldn't start session.");
	}
}
else {
	throw new \Exception("You can't edit this document.");
}
