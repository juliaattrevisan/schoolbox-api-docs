<?php
/**
 * Assessment creation. Assessments must always belong in a Category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$surveyHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$questionHandler = new Survey_Question();
$answerHandler = new Survey_Answer();
$questionBankHandler = new Survey_Question_Bank();
$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

if (!empty($_POST) && is_numeric($_POST['section_id']) && is_array($_POST['question_bank_id']) && count($_POST['question_bank_id'])) {
    $sectionId = $_POST['section_id'];

    /* Find out if you are the owner of the section before adding
     * Find out if the section exists first
     */
    $section = $sectionHandler->getByID($sectionId);

    /* Check if the section exists
     */
    if (!$section) {
        error(1);
    }

    /* Check if online assessment exists. If it does, check if you own it
     */
    if (!$surveyHandler->isValidUrlAccess($section['assess_id'])) {
        $this->pageNotFound();
    }

    $survey = $surveyHandler->getByID($section['assess_id']);

    if ($survey['user_id'] == $_SESSION['id']) {
        $allQuestionBankIDs = $_POST['question_bank_id'];

        foreach ($allQuestionBankIDs as $currentQuestionBankID) {
            $questionBank = $questionBankHandler->getByID($currentQuestionBankID);

            $questionId = $questionHandler->create([
                'section_id' => $sectionId,
                'type_id' => $questionBank['type_id'],
                'sequence' => $questionHandler->getNextSequence($sectionId),
                'marks' => $questionBank['marks'],
                'question' => $questionBank['question']
            ]);

            $answerId = $answerHandler->create([
                'question_id' => $questionId,
                'answer' => $questionBank['answer'],
            ]);
        }

        SitePage::localRedir("/survey/summary/{$survey['assess_id']}");
    } else {
        error(131);
    }

    exit();
}

error(2);
