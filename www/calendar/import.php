<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$groupHandler = new Group();

if ($groupHandler->getPermission($this->arg[0]) < WRITE_ACCESS) {
	error(19);
}
else {
	try {
		$categoryMapper = new \Schoolbox\Resource\CategoryMapper();
		$category = $categoryMapper->findById($this->arg[0]);

		$this->setFile(TEMPLATE_content, 'calendar/import.html');

		$this->setVar(array(
			'HOMEPAGE_ID' => $this->arg[0],
			'HOMEPAGE_NAME' => $category->getName()
		));
	}
	catch (\Exception $e) {
		error(13);
	}
}