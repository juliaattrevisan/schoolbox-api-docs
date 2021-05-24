<?php

/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$surveyHandler = new Survey_Main();
$factory = new Survey_Factory();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);


if ($surveyHandler->isDeleted($survey['assess_id'])) {
    error(112);
}

$surveyExporter = new Survey_Exporter($survey, $factory, new Survey_Section(), new Survey_Attempt(), new Survey_Question());

/* Optionally filter by question
 */
if (is_numeric($this->arg[1])) {
    $surveyExporter->setFilterByQuestion($this->arg[1]);
}

$surveyExporter->exportResponsesToCSV($survey['assess_id'], $this->arg[1], $this->arg[2]);
