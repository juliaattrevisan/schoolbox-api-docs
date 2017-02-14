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


use Aura\Router\Router;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;

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

/** @var Router $router */
$router = $this->getDi()->get('router');
$route = $router->generate('learningAssessment.getUpdateAssessmentForm', ['assessmentId' => $import->getAssessmentId()]);
SitePage::localRedir($route);

