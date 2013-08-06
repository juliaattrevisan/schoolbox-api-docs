<?php
/**
 * Question deletion and Answer deletion. All deletions are soft and any
 * questions that get soft-deleted also have their answers soft-deleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Assess_Main();
$questionHandler = new Assess_Question();
$answerHandler = new Assess_Answer();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();

/* Soft delete the question
 */
$question = $questionHandler->getByID($this->arg[0]);

if ($question) {
	$answer = $answerHandler->getForQuestion($question['question_id']);
	$section = $sectionHandler->getByID($question['section_id']);
}

if ($section) {
	$assess = $assessHandler->getByID($section['assess_id']);
}

if ($assessHandler->isDeleted($section['assess_id'])) {
	error(112);
}

if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(130);
}

if ($sectionHandler->isDeleted($section['section_id'])) {
	error(115);
}

/* Perform soft delete
 */
$questionHandler->update(array(
    'question_id' => $question['question_id'],
    'deleted_at' => 'NOW()',
));

$answerHandler->update(array(
	'question_id' => $question['question_id'],
    'answer_id' => $answer['answer_id'],
    'deleted_at' => 'NOW()',
));

/* Reorder questions because sequence corresponds to question number
 */
$section = $sectionHandler->getByID($question['section_id']);

$questionHandler->resequenceQuestionsAfterDelete($section['assess_id'], $section['section_id']);

SitePage::localRedir('/assess/modify/' . $section['assess_id']);

