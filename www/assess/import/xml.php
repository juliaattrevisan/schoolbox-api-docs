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

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;

/** @var SitePage $this */
$folderId = $this->arg[0];
$contextType = $_POST['context_type'];
$contextId = $_POST['context_id'];

/** @var FilesystemProxy $filesystemProxy */
$filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

if (!is_numeric($folderId) || empty($contextType) || !is_numeric($contextId)) {
    error(ErrorMapper::ERROR_SUBJECT_DELETED);
}

$collector = (new CollectorFactory())->build($_POST['attachment'] ?? []);
try {
    $file = $collector->getFirst();
    $data = $filesystemProxy->read($file);
} catch (\Schoolbox\Resource\Exception $e) {
    error(ErrorMapper::ERROR_IMPORT_FILE_NOT_FOUND);
}

SitePage::extendExecutionTime();

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
