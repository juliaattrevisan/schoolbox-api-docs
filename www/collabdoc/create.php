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

try{
	$folder = FolderMapper::getInstance()->findById($this->arg[0]));
}
catch (\Schoolbox\Mapper\Exception $e) {
	error(2);
}

if ($authenticator->canCreate($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'etherpad/create.html');

	$this->setVar(array(
		'HOMEPAGE_ID' => $this->arg[0],
		'FOLDER_NAME' => Scrub::text($folder->getName())
	));
}
else {
	error(19);
}


