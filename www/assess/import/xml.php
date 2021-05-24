<?php

/**
 * Quiz XML import.
 *
 * Uses the handlers to import from a Moodle-compatible format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;

/** @var SitePage $this */
$folderId = $this->arg[0];
$contextType = $_POST['context_type'];
$contextId = $_POST['context_id'];

if (!is_numeric($folderId) || empty($contextType) || !is_numeric($contextId)) {
    error(18);
}

$data = null;
if ($_FILES['importXML']['tmp_name']) {
    $data = file_get_contents($_FILES['importXML']['tmp_name']);
} else {
    error(134);
}

SitePage::extendExecutionTime(300);

/** @var AssessmentMapperProxy $assessmentMapperProxy */
$assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

$import = new Assess_Import_Xml(
    $this->arg[0],
    new Assess_Main(),
    new Assess_Section(),
    new Assess_Factory(),
    $data,
    $this,
    $assessmentMapperProxy,
    $contextType,
    $contextId
);
$import->import();
$quiz = $import->getQuiz();

// Redirect to Quiz Update page.
$route = $this->routeLookup('learningAssessment.getUpdateAssessmentForm', ['assessmentId' => $quiz->getId()]);

// Configure Update page to return to requested URL, or default to context homepage
if (isset($_GET[Redirect::RETURN_URL])) {
    $returnUrl = Redirect::decode($_GET[Redirect::RETURN_URL]);
} else {
    $returnUrl = $quiz->getContext() instanceof Project
        ? $this->routeLookup('learningAssessment.getStatus', ['assessmentId' => $quiz->getContext()->getId()])
        : "/homepage/{$quiz->getBaseContext()->getFolder()->getId()}";
}
SitePage::localRedir($route . Redirect::queryString($returnUrl));
