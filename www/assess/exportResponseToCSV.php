<?php

/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$assessHandler = new Assess_Main();
$factory = new Assess_Factory();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$assess = $assessHandler->getByID($this->arg[0]);


if ($assessHandler->isDeleted($assess['assess_id'])) {
    error(112);
}

$assessExporter = new Assess_Exporter($assess, $factory, new Assess_Section(), new Assess_Attempt(), new Assess_Question());

/* Optionally filter by question
 */
if (is_numeric($this->arg[1])) {
    $assessExporter->setFilterByQuestion($this->arg[1]);
}

$assessExporter->exportResponsesToCSV($assess['assess_id'], $this->arg[1], $this->arg[2]);
