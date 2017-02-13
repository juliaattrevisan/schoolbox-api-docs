<?php
/**
 * Quiz XML import.
 *
 * Uses the handlers to import from a Moodle-compatible format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */


use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;

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
$assessmentMapper = AssessmentMapper::getInstance();

$import = new Assess_Import_Xml(
    $this->arg[0],
    new Assess_Main(),
    new Assess_Section(),
    new Assess_Factory(),
    $data,
    $this,
    $assessmentMapper,
    $contextType,
    $contextId
);
$import->import();

SitePage::localRedir('/assess/summary/' . $import->getAssessId());
