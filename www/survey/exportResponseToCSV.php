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

$assessHandler = new Survey_Main();
$factory = new Survey_Factory();

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

$surveyExporter = new Survey_Exporter($assess, $factory, new Survey_Section(), new Survey_Attempt(), new Survey_Question());

/* Optionally filter by question
 */
if (is_numeric($this->arg[1])) {
	$surveyExporter->setFilterByQuestion($this->arg[1]);
}

$surveyExporter->exportResponsesToCSV($assess['assess_id'], $this->arg[1], $this->arg[2]);
