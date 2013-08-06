<?php
/**
 * Assessment creation. Assessments must always belong in a Category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$questionHandler = new Survey_Question();
$answerHandler = new Survey_Answer();
$questionBankHandler = new Survey_Question_Bank();
$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

if (!empty($_POST) && is_numeric($_POST['section_id']) && is_array($_POST['question_bank_id']) && count($_POST['question_bank_id'])) {
    $section_id = $_POST['section_id'];

    /* Find out if you are the owner of the section before adding
      * Find out if the section exists first
      */
    $section = $sectionHandler->getByID($section_id);

    /* Check if the section exists
      */
    if (!$section) {
        error(1);
    }

    $assess = $assessHandler->getByID($section['assess_id']);

    /* Check if online assessment exists. If it does, check if you own it
      */
    if (!$assess) {
        error(112);
    }

    if ($assess['user_id'] == $_SESSION['id']) {
        $allQuestionBankIDs = $_POST['question_bank_id'];

        foreach ($allQuestionBankIDs as $currentQuestionBankID) {
            $questionBank = $questionBankHandler->getByID($currentQuestionBankID);

            $question_id = $questionHandler->create(array(
                'section_id' => $section_id,
                'type_id' => $questionBank['type_id'],
                'sequence' => $questionHandler->getNextSequence($section_id),
                'marks' => $questionBank['marks'],
                'question' => $questionBank['question']
            ));

            $answer_id = $answerHandler->create(array(
                'question_id' => $question_id,
                'answer' => $questionBank['answer'],
            ));
        }

        SitePage::localRedir('/survey/modify/' . $assess['assess_id']);
    } else {
        error(131);
    }

    exit();
} else {
    error(2);
}