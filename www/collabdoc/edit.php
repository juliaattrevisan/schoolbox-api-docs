<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

/* Check if the currently logged in user can participate
 */
$authenticator = new \Etherpad\Authenticator();

/* Load the pad from our db to get its groupId
 * Error out if you can't find it
 */
$padMapper = new \Etherpad\PadMapper();

try {
	$pad = $padMapper->findById($this->arg[0]);
	$folder = FolderMapper::getInstance()->findById($pad->getHomepageId());
}
catch (\Exception $e) {
	error(19);
}

/* If you can participate, create a session and off you go
 * Loade le pahde in le olde ifrayme
 */
if ($authenticator->isParticipator($pad->getHomepageId(), $_SESSION['user']->getId())) {
	$this->setFile(TEMPLATE_content, 'etherpad/edit.html');

	$this->setBlock(TEMPLATE_content, 'etherpad_lock', 'etherpad_lock_block');
	$this->setBlock(TEMPLATE_content, 'etherpad_unlock', 'etherpad_unlock_block');

	$this->setVar(array(
		'etherpad_lock_block' => '',
		'etherpad_unlock_block' => '',
	));

	/* Set lock/unlock butonye
	 * Provide buttons for those with Create powers
	 * Otherwise hide them altogether
	 */
	if ($authenticator->canCreate($pad->getHomepageId())) {
		if ($pad->isLocked()) {
			$this->parse('etherpad_unlock_block', 'etherpad_unlock');
		}
		else {
			$this->parse('etherpad_lock_block', 'etherpad_lock');
		}
	}

	/* The rest of the pad
	 */
	$this->setVar(array(
		'HOMEPAGE_ID' => $this->arg[1],
		'FOLDER_NAME' => Scrub::text($folder->getName()),
		'ETHERPAD_PAD_ID' => $pad->getId(),
		'ETHERPAD_NAME' => Scrub::text($pad->getDisplayName()),
		'ETHERPAD_URL' => "/collabdoc/load/{$pad->getId()}"
	));
}
else {
	error(19);
}
