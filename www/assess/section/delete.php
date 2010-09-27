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
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-section.php');
require_once '../library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(130);
}

/* Get a list of questions and answers to softdelete
 */
$questionHandler = new AssessQuestion();
$answerHandler = new AssessAnswer();

$questionList = $questionHandler->getList($this->arg[0]);

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
$sectionHandler = new AssessSection();

$section = $sectionHandler->getByID($this->arg[0]);
$section['deleted_at'] = 'NOW()';
$sectionHandler->update($section);

$sectionHandler->resequenceSectionsAfterDelete($section['assess_id']);

localRedir('/assess/modify/' . $section['assess_id']);
?>