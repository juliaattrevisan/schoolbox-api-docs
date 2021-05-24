<?php

/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$factory         = new Assess_Factory();
$questionHandler = new Assess_Question();
$sectionHandler  = new Assess_Section();
$assessHandler   = new Assess_Main();
$groupHandler    = new Group();

$currentQuestion = $questionHandler->getById($this->arg[0]);
$section         = $sectionHandler->getByID($currentQuestion['section_id']);
$assess          = $assessHandler->getBYID($section['assess_id']);

if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
    $this->pageNotFound();
}

if (!$currentQuestion) {
    error(119);
}

if (
    !$assessHandler->isOwner($assess['assess_id'])
    && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)
) {
    error(125);
}

/* Set up template
 */
$this->setFile('xml', 'assess/question/exportToMoodle.xml');
$this->setBlock('xml', 'answerInputs', 'answerInputs_block');

/* This if() clause allows for sections containing no questions.
 * because they come out with getContents()
 */
if ($currentQuestion['type_id']) {
    $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

    /* Load answer template into answerOption block
     */
    $this->setFile(
        'answerFormFragment',
        'assess/answer/' . $factory->getQuestionType($currentQuestion['type_id']) . '/exportToMoodle.xml'
    );
    $this->setBlock('answerFormFragment', 'answerOption', 'answerOption_block');

    $this->setVar(
        array(
            'QUESTION_QUESTION'    => Scrub::text($currentQuestion['question']),
            'QUESTION_MARKS'       => $currentQuestion['marks'],
            'QUESTION_TYPE_ID'     => $currentQuestion['type_id'],
            'QUESTION_TYPE_MOODLE' => $factory->getMoodleQuestionType($currentQuestion['type_id']),
        )
    );

    /* Load individual answers
     */
    $answerHandler->exportMoodleAnswer($this, $currentQuestion['question_id']);

    $this->parse('answerInputs_block', 'answerFormFragment', true);
}

/* Create a filename
 */
$filename = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', 'assess_question') . '-' . $currentQuestion['question_id'] . '.xml';

header('Content-type: text/xml');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$this->injectGlobalVars();
$this->template->pparse('finaloutput', 'xml');

/* Prevent other template crap from rendering ;)
 */
die();
