<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$groupHandler = new Group();
$surveyHandler = new Survey_Main();

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
	$this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);

if ($surveyHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < WRITE_ACCESS)) {
	error(125);
}

$questionHandler = new Survey_Question();
$questionHandler->reorderQuestions($_POST['ids']);
exit();