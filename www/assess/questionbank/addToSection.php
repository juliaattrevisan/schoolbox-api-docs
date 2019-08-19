<?php
/**
 * Add Question to Quiz via Questionbank.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Learning\Assessment\Redirect;

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$questionHandler = new Assess_Question();
$answerHandler = new Assess_Answer();
$questionBankHandler = new Assess_Question_Bank();
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

    /* Check if online assessment exists and URL is valid. If it does, check if you own it
      */
    if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
        $this->pageNotFound();
    }

    $assess = $assessHandler->getByID($section['assess_id']);

    if ($assess['user_id'] == $_SESSION['id']) {
        $allQuestionBankIDs = $_POST['question_bank_id'];

        foreach ($allQuestionBankIDs as $currentQuestionBankID) {
            $questionBank = $questionBankHandler->getByID($currentQuestionBankID);

            $questionId = $questionHandler->create([
                'section_id' => $sectionId,
                'type_id' => $questionBank['type_id'],
                'sequence' => $questionHandler->getNextSequence($sectionId),
                'marks' => $questionBank['marks'],
                'question' => $questionBank['question'],
				'config' => $questionBank['config'],
            ]);

            $answer_id = $answerHandler->create([
                'question_id' => $questionId,
                'answer' => $questionBank['answer'],
            ]);
        }

        $returnUrl = $_POST[Redirect::RETURN_URL] != '' ? '?'.Redirect::RETURN_URL."={$_POST[Redirect::RETURN_URL]}" : '';

        SitePage::localRedir("/assess/summary/{$assess['assess_id']}{$returnUrl}");
    } else {
        error(131);
    }

    exit();
} else {
    error(2);
}
