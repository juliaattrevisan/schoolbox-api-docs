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

$assessHandler = new Survey_Main();
$questionHandler = new Survey_Question();
$answerHandler = new Survey_Answer();
$sectionHandler = new Survey_Section();
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
$questionHandler->resequenceQuestionsAfterDelete($section['assess_id'], $section['section_id']);

SitePage::localRedir('/survey/summary/' . $section['assess_id']);

