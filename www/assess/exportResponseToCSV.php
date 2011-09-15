<?php
/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/Assess.php');
require_once('../library/assess/AssessSection.php');
require_once('../library/assess/AssessQuestion.php');
require_once('../library/assess/AssessAnswer.php');
require_once('../library/assess/AssessFactory.php');
require_once('../library/assess/AssessAttempt.php');
require_once('../library/assess/AssessExporter.php');

$assessHandler = new Assess();
$factory = new AssessFactory();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
	error(112);
}

$assessExporter = new AssessExporter($assess, $factory, new AssessSection(), new AssessAttempt(), new AssessQuestion());

/* Optionally filter by question
 */
if (is_numeric($this->arg[1])) {
	$assessExporter->setFilterByQuestion($this->arg[1]);
}

$assessExporter->exportResponsesToCSV($assess['assess_id'], $this->arg[1]);
