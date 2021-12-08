<?php

/**
 * Reorder quiz questions if you have access permissions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;

$groupHandler  = new Group();
$assessHandler = new Assess_Main();

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$assess = $assessHandler->getByID($this->arg[0]);

if ($assessHandler->isDeleted($this->arg[0])) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_QUIZ_NOT_FOUND);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW_2);
}

$questionHandler = new Assess_Question();
$questions = $questionHandler->getAll($this->arg[0]);
//confirm the questions are actually from the quiz
foreach ($questions as $question) {
    if (!in_array($question['question_id'], $_POST['ids'])) {
        //question that was reordered, is not part of the quiz, exit here
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW_2);
    }
}
$questionHandler->reorderQuestions($_POST['ids']);
exit();
