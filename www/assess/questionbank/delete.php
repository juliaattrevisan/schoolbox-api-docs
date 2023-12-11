<?php

/**
 * Delete
 *
 * Softdeletes a Questionbank Category
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

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

//// not in SitePage context?
//if (!isset($this)) {
//    http_response_code(404);
//    die('No direct access permitted');
//}
///** @var SitePage $this */
//$this->initSession();
//
//$groupHandler = new Group();
//
//if (!$groupHandler->isStaff()) {
//    error(126);
//}
//
//$categoryHandler = new Assess_Question_Category();
//
//if (is_numeric($this->arg[0])) {
//    $categoryHandler->delete($categoryHandler->getByID($this->arg[0]));
//}
//
///* Back to the future -_-
// */
//SitePage::backToTheFuture(1);
