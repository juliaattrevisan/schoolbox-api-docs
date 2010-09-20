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
require_once '../library/assess/assess-factory.php';
require_once '../library/assess/assess-section.php';

/* Soft delete the question
 */
$questionHandler = new AssessQuestion();
$answerHandler = new AssessAnswer();

$question = $questionHandler->getByID($this->arg[0]);
$answer = $answerHandler->getForQuestion($question['question_id']);

/* Perform soft delete
 */
$questionHandler->update(array(
    'question_id' => $question['question_id'],
    'deleted_at' => 'NOW()',
));

$answerHandler->update(array(
    'answer_id' => $answer['answer_id'],
    'deleted_at' => 'NOW()',
));

/* Reorder questions because sequence corresponds to question number
 */
$sectionHandler = new AssessSection();

$section = $sectionHandler->getByID($question['section_id']);

$questionHandler->resequenceQuestionsAfterDelete($section['assess_id'], $section['section_id']);

localRedir('/assess/modify/' . $section['assess_id']);
?>