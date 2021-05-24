<?php

/**
 * Survey deletion. Sections, Questions and Answers are all softdeleted if
 * the survey is softdeleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$surveyHandler   = new Survey_Main();
$questionHandler = new Survey_Question();
$answerHandler   = new Survey_Answer();
$sectionHandler  = new Survey_Section();
$groupHandler    = new Group();
/* Proceed regardless of whether the section has been previously erased or not
 *

if ($surveyHandler->isDeleted($this->arg[0])) {
 error(112);
}

 *
 */
if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);
if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(126);
}

$sectionList = $sectionHandler->getList($this->arg[0]);
if (is_array($sectionList) && count($sectionList)) {
    foreach ($sectionList as $currentSection) {
        $section = $sectionHandler->getByID($currentSection['section_id']);
        $questionList = $questionHandler->getList($currentSection['section_id']);
        if (is_array($questionList) && count($questionList)) {
            foreach ($questionList as $currentQuestion) {
                $question = $questionHandler->getByID($currentQuestion['question_id']);
                $answer = $answerHandler->getForQuestion($currentQuestion['question_id']);
                /* Perform soft delete
                 */
                $questionHandler->update(array(
                    'question_id' => $currentQuestion['question_id'],
                    'deleted_at' => 'NOW()',
                ));
                $answerHandler->update(array(
                            'question_id' => $currentQuestion['question_id'],
                            'answer_id' => $answer['answer_id'],
                            'deleted_at' => 'NOW()',
                ));
            }
        }

        $section['deleted_at'] = 'NOW()';
        $sectionHandler->update($section);
    }
}

/* Remove assess
 */
$surveyHandler->update(array(
    'assess_id' => $this->arg[0],
    'deleted_at' => 'NOW()'
));
$this->setFile(TEMPLATE_content, 'survey/deleted.html');
$this->setVar(array(
    'CATEGORY_ID' => $survey['category_id'],
));
