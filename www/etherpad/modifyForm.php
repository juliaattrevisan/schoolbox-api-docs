<?php
/**
 * Create a new group pad
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$homepageId = $_POST['homepageId'];
$name = $_POST['name'];

/* Check that you have write permissions
 */
$authenticator = new \Etherpad\Authenticator();

if ($authenticator->canCreate($homepageId)) {

	/* Make life a bit easier for Etherpad by using a hashed version
	 * of the name, rather than let it get urlencoded in some strange
	 * way by Etherpad itself
	 */
	$padName = md5($name);

	/* Create a new group pad
	 */
	$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));

	/* Create a new pad, get group id
	 * Create a group pad
	 * Spawn a new group of users
	 * Assign them to a group
	 */
	$groupResult = $client->createGroup();
	$response = $groupResult->getResponse();

	if ($response['message'] == 'ok') {
		$groupId = $response['data']['groupID'];

		/* Create a group pad
		 */
		$createResult = $client->createGroupPad($groupId, $padName);
		$response = $createResult->getResponse();

		if ($response['message'] == 'ok') {
			$userMapper = new \Schoolbox\UserMapper();
			$allUsers = new \Schoolbox\Collection();

			/* Spawn a new group of users
			 */
			$allUsers->add($userMapper->findByColumn('username', 'alaress')->first());
			$allUsers->add($userMapper->findByColumn('username', 'cglenn')->first());
			$allUsers->add($userMapper->findByColumn('username', 'asaxon')->first());
			$allUsers->add($userMapper->findByColumn('username', 'wwhite')->first());

			/* Assign them to a group
			 */
			$etherpadGroupMapper = new \Etherpad\GroupMapper();

			foreach($allUsers as $currentUser) {
				$etherpadGroup = new \Etherpad\Group();
				$etherpadGroup->setGroupId($groupId);
				$etherpadGroup->setUserId($currentUser->getId());
				$etherpadGroup->setHomepageId($homepageId);

				$etherpadGroupMapper->save($etherpadGroup);

				/* Add them as etherpad authors
				 */
				$client->createAuthorIfNotExistsFor($currentUser->getId(), $currentUser->getFullName());
			}

			/* Get pad id to set to private and to passs it as a redirect element.
			 * Note that padId's come in the form g.abcdefg$padName
			 */
			$padId = $response['data']['padID'];

			/* Must pass strings to change the status - like the rest of this box
			 * https://github.com/ether/etherpad-lite/issues/561
			 */
			$client->setPublicStatus($padId, 'false');

			/* Spawn a record in our own db
			 */
			$newPad = new \Etherpad\Pad();
			$newPad->setName($name);
			$newPad->setBody('');
			$newPad->setPadId($padId);
			$newPad->setGroupId($groupId);
			$newPad->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			$newPad->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

			$padMapper = new \Etherpad\PadMapper();
			$padMapper->save($newPad);

			/* Go to edit screen for now
			 */
			$this->localRedir("/etherpad/modify/{$newPad->getId()}");
		}
		else {
			throw new \Exception('Something strange happened when creating a new pad');
		}
	}
	else {
		throw new \Exception('Something strange happened when creating a group id');
	}
}
else {
	error(19);
}



