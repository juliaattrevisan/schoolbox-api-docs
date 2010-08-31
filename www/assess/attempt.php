<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-factory.php';
require_once '../library/assess/assess-section.php';
require_once '../library/assess/assess-attempt.php';

$factory = new AssessFactory();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$sectionHandler = new AssessSection();

$assess = $assessHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);
$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);

/* TODO: Error checking for handlers
 */

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/attempt.html');
$this->setBlock(TEMPLATE_content, 'question', 'question_block');
$this->setBlock(TEMPLATE_content, 'continueAssess', 'continueAssess_block');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setTitle('Assessment: ' . $assess['title']);

/* Show current Section's data, and other mandatory tokens needed to complete
 * the Assessment.
 */
$attemptStartTime = strtotime($attempt['start']);

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $assess['assess_id'],
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_DURATION' => $assess['duration'] - (time() - $attemptStartTime),
    'ATTEMPT_START' => date('jS F Y, g:i:sa', $attemptStartTime),
    'ATTEMPT_END' => date('jS F Y, g:i:sa', ($attemptStartTime + $assess['duration'])),
    'SECTION_TITLE' => $section['title'],
));

/* Load all the questions for this section
 */
$allQuestions = $sectionHandler->getContents($this->arg[0], $this->arg[1]);

foreach($allQuestions as $currentQuestion) {
    $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

    $this->setVar(array(
        'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
        'QUESTION_SEQUENCE' => $currentQuestion['sequence'],
        'QUESTION_QUESTION' => $currentQuestion['question'],
        'ANSWER_TEMPLATE' => $answerHandler->loadTemplate($factory->getQuestionType($currentQuestion['type_id'])),
    ));

    /* Load individual answers
     */
    $allAnswers = $answerHandler->getByQuestionID($currentQuestion['question_id']);

    foreach($allAnswers as $currentAnswer) {
        $this->setVar(array(
            'ANSWER_ID' => $currentAnswer['answer_id'],
            'ANSWER' => '',
        ));
    }

    $this->parse('question_block', 'question', true);
}

/* Render Continue/Finish buttons where appropriate
 */
$nextSection = $sectionHandler->getAfter($this->arg[0], $section['sequence']);

if (!is_numeric($nextSection['section_id'])) {
    $this->setVar('continueAssess_block', '');
    $this->parse('endAssess_block', 'endAssess');
}
else {
    $this->setVar(array(
        'NEXT_SECTION_ID' => $nextSection['section_id'],
    ));

    $this->parse('continueAssess_block', 'continueAssess');
    $this->setVar('endAssess_block', '');
}

/* Set the start time if not already done
 */
if (!is_numeric($_SESSION['attempt_id'])) {
    $_SESSION['attempt_id'] = $attemptHandler->start($_SESSION['id'], $this->arg[0]);
}
?>