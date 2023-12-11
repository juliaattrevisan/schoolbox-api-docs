<?php

/**
 * Question deletion and Answer deletion. All deletions are soft and any
 * questions that get soft-deleted also have their answers soft-deleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* Redirection to the new learning/quiz/{id}/content page */
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;

$assessmentId = null;

/** @var SitePage $this */
if (is_numeric($this->arg[0])) {
    //Is the quiz still there?
    try {
        $quiz = QuizMapper::getInstance()->findById($this->arg[0]);
        $assessmentId = $quiz->getAssessmentModel()->getId();
    } catch (\Exception $e) {
        $this->pageNotFound();
    }
}
if ($assessmentId > 0) {
    $this::localRedir('/learning/quiz/' . $assessmentId . '/content', null, 301);
} else {
    $this->pageNotFound();
}

//use Schoolbox\Learning\Assessment\Redirect;
//use Schoolbox\Permission\Access\UserAccess;
//
//$assessHandler = new Assess_Main();
//$questionHandler = new Assess_Question();
//$answerHandler = new Assess_Answer();
//$sectionHandler = new Assess_Section();
//$groupHandler = new Group();
//$responseHandler = new Assess_Response();
//
///* Soft delete the question
// */
//$question = $questionHandler->getByID($this->arg[0]);
//if ($question) {
//    $answer = $answerHandler->getForQuestion($question['question_id']);
//    $section = $sectionHandler->getByID($question['section_id']);
//}
//
//if ($section) {
//    $assess = $assessHandler->getByID($section['assess_id']);
//}
//
//if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
//    $this->pageNotFound();
//}
//
//if ($assessHandler->isDeleted($section['assess_id'])) {
//    error(112);
//}
//
//if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
//    error(130);
//}
//
//if ($sectionHandler->isDeleted($section['section_id'])) {
//    error(115);
//}
//
///* Perform soft delete
// */
//$questionHandler->update(array(
//    'question_id' => $question['question_id'],
//    'deleted_at' => Alaress_Date_Format::now()->getDatabaseDate(),
//));
//$answerHandler->update(array(
//    'question_id' => $question['question_id'],
//    'answer_id' => $answer['answer_id'],
//    'deleted_at' => Alaress_Date_Format::now()->getDatabaseDate(),
//));
//
///* Delete this question's usage from assess_response
// * These rows aggregate into a person's Attempt
// */
//$responseHandler->deleteQuestionFromResponses((int)$question['question_id']);
//
///* Reorder questions because sequence corresponds to question number
// */
//$section = $sectionHandler->getByID($question['section_id']);
//$questionHandler->resequenceQuestionsAfterDelete($section['assess_id'], $section['section_id']);
//$returnUrl = $_GET[Redirect::RETURN_URL] != '' ? "?" . Redirect::RETURN_URL . "={$_GET[Redirect::RETURN_URL]}" : "";
//SitePage::localRedir('/assess/summary/' . $section['assess_id'] . $returnUrl);