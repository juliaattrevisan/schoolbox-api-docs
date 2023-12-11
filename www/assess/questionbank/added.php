<?php

/* There's no real allegory to our legacy Question Bank - in learnosity era,
 * every item is in the itembank already. The only place you access that is
 * via learnosity quiz content authoring.
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

$this->setAjax();
$this->setFile(TEMPLATE_content, 'assess/questionbank/added.html');

$this->setVar([
    'QUESTION_ID'        => $this->arg[0],
    'ASSESS_KIND_PLURAL' => 'quizzes',
]);

$this->injectGlobalVars();
$this->template->pparse('finaloutput', TEMPLATE_content);
