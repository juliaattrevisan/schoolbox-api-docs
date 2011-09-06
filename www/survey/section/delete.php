<?php
/**
 * Section deletion. Any Questions contained within the section, along with
 * those Questions' Answers, are softdeleted when the section is softdeleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/survey/Survey.php');
require_once('../library/survey/SurveySection.php');
require_once('../library/survey/SurveyQuestion.php');
require_once('../library/survey/SurveyAnswer.php');
require_once '../library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(130);
}

/* Get a list of questions and answers to softdelete
 */
$assessHandler = new Survey();
$sectionHandler = new SurveySection();
$questionHandler = new SurveyQuestion();
$answerHandler = new SurveyAnswer();

$questionList = $questionHandler->getList($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[0]);

if ($assessHandler->isDeleted($section['assess_id'])) {
	error(112);
}

if ($questionList) {
    foreach($questionList as $currentQuestion) {
        $question = $questionHandler->getByID($currentQuestion['question_id']);
        $answer = $answerHandler->getForQuestion($currentQuestion['question_id']);

        /* Perform soft delete
         */
        $questionHandler->update(array(
            'question_id' => $currentQuestion['question_id'],
            'deleted_at' => 'NOW()',
        ));

        $answerHandler->update(array(
            'answer_id' => $answer['answer_id'],
            'deleted_at' => 'NOW()',
        ));
    }
}

/* Reorder sections because sequence corresponds to section number
 */
$section['deleted_at'] = 'NOW()';
$sectionHandler->update($section);

$sectionHandler->resequenceSectionsAfterDelete($section['assess_id']);

SitePage::localRedir('/survey/modify/' . $section['assess_id']);

