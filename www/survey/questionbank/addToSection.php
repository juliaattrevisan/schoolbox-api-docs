<?php
/**
 * Add Question to Survey via Questionbank.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper;

$surveyHandler       = new Survey_Main();
$sectionHandler      = new Survey_Section();
$questionHandler     = new Survey_Question();
$answerHandler       = new Survey_Answer();
$questionBankHandler = new Survey_Question_Bank();
$groupHandler        = new Group();

if (!empty($_POST) && is_numeric($_POST['section_id'])) {
    $sectionId = $_POST['section_id'];

    /* Find out if the section exists first
     */
    $section = $sectionHandler->getByID($sectionId);

    /* Check if the section exists
     */
    if (!$section) {
        error(1);
    }

    /* Check if online assessment exists. If it does, check if you have write access to it
     */
    if (!$surveyHandler->isValidUrlAccess($section['assess_id'])) {
        $this->pageNotFound();
    }

    $survey = $surveyHandler->getByID($section['assess_id']);

    /* Only write-accessors can do this action
     */
    $folder = (new Mapper())->findById($survey['category_id']);
    $isInstructor = (Access::getInstance())->isInstructor($_SESSION['user'], $folder);

    /* Not an instructor? You can't continue
     */
    if (!$isInstructor) {
        error(126);
    }

    /* If you're an Instructor and you have question bank details submitted
     * try to process the request
     */
    if ($isInstructor && is_array($_POST['question_bank_id']) && count($_POST['question_bank_id'])) {
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

        exit();
    }

    error(131);
}

error(2);
