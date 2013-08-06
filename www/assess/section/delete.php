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

/* Get a list of questions and answers to softdelete
 */
$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$questionHandler = new Assess_Question();
$answerHandler = new Assess_Answer();
$groupHandler = new Group();

$questionList = $questionHandler->getList($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[0]);

if ($section) {
	$assess = $assessHandler->getByID($section['assess_id']);
}

if (!$assess) {
	error(112);
}

if (!$assessHandler->isOwner($section['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(130);
}

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
			'question_id' => $currentQuestion['question_id'],
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

SitePage::localRedir('/assess/modify/' . $section['assess_id']);

