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
$assessHandler = new Assess_Main();

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
	error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
	error(125);
}

$questionHandler = new Assess_Question();
$questionHandler->reorderQuestions($_POST['ids']);
exit();