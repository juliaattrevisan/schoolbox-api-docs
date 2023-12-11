<?php

/**
 * Remove a question from the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
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

//$groupHandler = new Group();
//if (!$groupHandler->isStaff()) {
//    error(126);
//}
//
//$questionBankHandler = new Assess_Question_Bank();
//if (is_numeric($this->arg[0])) {
//    $questionBankHandler->update(array(
//            'question_bank_id' => $this->arg[0],
//            'deleted_at' => Alaress_Date_Format::now()->getDatabaseDate(),
//        ));
//}
//
//$this->backToTheFuture(1);
