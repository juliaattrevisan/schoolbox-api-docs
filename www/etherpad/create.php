<?php
/**
 * Create a new group pad
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* TODO: Test values
 */
$apiKey = 'as90dfuasdifjsdopaifjuhas9p';
$homepageId = 52;
$padName = 'The Ultimate Bart';
$defaultText = 'Spinebreaking new text';

/* Create a new group pad
 */
$client = new \EtherpadLite\Client($apiKey);

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
		}

		/* Get pad id
		 */
		$padId = $response['data']['padID'];

		$this->localRedir("/etherpad/edit/{$groupId}/{$_SESSION['user']->getId()}");
	}
}
else {
	throw new \Exception('Something strange happened when creating a group id');
}



