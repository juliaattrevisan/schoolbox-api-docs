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

/* Check that you have write permissions
 */
$authenticator = new \Etherpad\Authenticator();

try {
	$etherpadMapper = new \Etherpad\PadMapper();
	$etherpad = $etherpadMapper->findById($this->arg[0]);

	$folder = FolderMapper::getInstance()->findById($etherpad->getHomepageId());

}
catch (\Exception $e) {
	error(164);
}

if ($authenticator->canCreate($etherpad->getHomepageId())) {
	$this->setFile(TEMPLATE_content, 'etherpad/modify.html');
	$this->setBlock(TEMPLATE_content, 'etherpad_delete', 'etherpad_delete_block');

	$this->setVar(array(
		'HOMEPAGE_ID' => $etherpad->getHomepageId(),
		'FOLDER_NAME' => Scrub::text($folder->getName()),
		'ETHERPAD_PAD_ID' => $etherpad->getId(),
		'ETHERPAD_PAD_NAME' => $etherpad->getName()
	));

	$this->parse('etherpad_delete_block', 'etherpad_delete');
}
else {
	error(19);
}


