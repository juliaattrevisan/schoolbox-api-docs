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

/* Create a new group pad
 */
$client = new \EtherpadLite\Client($apiKey);

/* Create a new pad, get group id
 * Spawn a new group of users
 * Assign them to a group
 * Create a group pad
 */
$groupResult = $client->createGroup();
$response = $groupResult->getResponse();

if ($response['message'] == 'ok') {
	$groupId = $response['data']['groupID'];

	/* Spawn a new group of users
	 */
	$userMapper = new \Schoolbox\UserMapper();
	$allUsers = new \Schoolbox\Collection();

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

	die();
}
else {
	throw new \Exception('Something strange happened when creating a group id');
}



