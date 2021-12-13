<?php

/**
 * Reorder survey questions if you have access permissions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;

$groupHandler  = new Group();
$surveyHandler = new Survey_Main();

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);

if ($surveyHandler->isDeleted($this->arg[0])) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_QUIZ_NOT_FOUND);
}

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW_2);
}

$questionHandler = new Survey_Question();
$questionHandler->reorderQuestions($this->arg[0], $_POST['sectionId'], $_POST['ids']);
exit();
