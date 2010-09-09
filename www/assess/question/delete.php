<?php
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