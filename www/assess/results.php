<?php

/* Redirection to the new learning/assessments/{id}/status page */
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;

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
    $this::localRedir('/learning/assessments/' . $assessmentId . '/status', null, 301);
} else {
    $this->pageNotFound();
}
