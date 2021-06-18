<?php

/**
 * Reorder quiz questions if you have access permissions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$groupHandler  = new Group();
$assessHandler = new Assess_Main();

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$assess = $assessHandler->getByID($this->arg[0]);

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(125);
}

$questionHandler = new Assess_Question();
$questionHandler->reorderQuestions($_POST['ids']);
exit();
