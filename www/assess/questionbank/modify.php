<?php

/**
 * Move
 *
 * Moves a question around in the Questionbank Category tree, reassigning
 * parent_ids where appropriate based on $_GET['position']
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

//require_once __DIR__ . '/../../../conf/core/require.php';
//$kernel = new \Schoolbox\Application\WebRequest\Kernel();
//
///** @var SitePage $page */
//$page = $kernel->get('sitePage');
//$page->initSession();
//
//$groupHandler = new Group();
//
//if ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
//    $categoryHandler = new Assess_Question_Category();
//
//    $newCategory = array(
//        'question_category_id' => $_POST['questionCategoryId'],
//        'name' => $_POST['name'],
//    );
//
//    $categoryHandler->update($newCategory);
//    SitePage::backToTheFuture(1);
//} else {
//    error(126);
//}
