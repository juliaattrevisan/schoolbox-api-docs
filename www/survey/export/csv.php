<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$surveyHandler = new Survey_Main();
$groupHandler  = new Group();

/** @var SitePage $this */
if (!is_numeric($this->arg[0] ?? false)) {
    error(112);
}

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);

if ($surveyHandler->isDeleted($survey['assess_id'])) {
    error(112);
}

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(125);
}

$exporter = new Survey_Export_CSV($this->arg[0], new Survey_Factory(), new Survey_Attempt(), new Survey_Question());

if (is_numeric($this->arg[1] ?? false)) {
    $exporter->setQuestionFilter($this->arg[1]);
}

/* Generate output
 */
$exporter->generate();

/* Check if Anonymous Result is set
 */
$exporter->adjustAnonymousResult($survey);

/* Stream output
 */
$exporter->activateTheGrid();

exit(1);
