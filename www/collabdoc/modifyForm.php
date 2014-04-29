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
	 *
	 * Use an INSTANCE_ID value as a prefix, to help prevent namespace
	 * collisions when generating $padName values (in case someone else
	 * uses the same name as you - etherpad services are shared between
	 * the live and dev environments, so we use INSTANCE_ID to keep 'em
	 * separated to some extent)
	 */
	$padName = md5(INSTANCE_ID . '_' . $name);

	/* Create a new group pad
	 */
	$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));

	/* Create a new pad, get group id
	 * Create a group pad
	 * Spawn a new group of users
	 * Assign them to a group
	 */
	$groupResult = $client->createGroup();

	if ($groupResult->getMessage() == 'ok') {
		$data = $groupResult->getData();
		$groupId = $data['groupID'];

		/* Create a group pad
		 */
		$createResult = $client->createGroupPad($groupId, $padName);

		if ($createResult->getMessage() == 'ok') {
			$userMapper = new \Schoolbox\UserMapper();
			$allUsers = new \Schoolbox\Collection();

			/* Get pad id to set to private and to pass it as a redirect element.
			 * Note that padId's come in the form g.abcdefg$padName
			 */
			$data = $createResult->getData();
			$padId = $data['padID'];

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
			$newPad->setHomepageId($homepageId);
			$newPad->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			$newPad->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

			$padMapper = new \Etherpad\PadMapper();
			$padMapper->save($newPad);

			/* Go to edit screen for now
			 */
			$this->localRedir("/collabdoc/modify/{$newPad->getId()}/{$homepageId}");
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