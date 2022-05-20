<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$assessHandler = new Assess_Main();
$groupHandler  = new Group();

/** @var SitePage $this */
if (!is_numeric($this->arg[0] ?? false)) {
    error(112);
}

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$assess = $assessHandler->getByID($this->arg[0]);


if ($assessHandler->isDeleted($assess['assess_id'])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(125);
}

$exporter = new Assess_Export_CSV($this->arg[0], new Assess_Factory(), new Assess_Attempt(), new Assess_Question());

if (is_numeric($this->arg[1] ?? false)) {
    $exporter->setQuestionFilter($this->arg[1]);
}

$exporter->generate();
$exporter->activateTheGrid();

exit(1);
