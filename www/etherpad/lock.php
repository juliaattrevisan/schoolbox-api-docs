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
$groupMapper = new \Etherpad\GroupMapper();
$group = $groupMapper->findByColumn('group_id', $pad->getGroupId())->first();

if ($authenticator->canCreate($group->getHomepageId())) {
	if ($pad->isLocked()) {
		$pad->unlock();
	}
	else {
		$pad->lock();
	}

	$padMapper->save($pad);

	$this->localRedir("/etherpad/modify/{$pad->getId()}");
}
else {
	error(19);
}