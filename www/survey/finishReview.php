<?php
/**
 * Review completion. Marks when an assessment review is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/survey/Survey.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/survey/SurveyAttempt.php';

$assessHandler = new Survey();
$attemptHandler = new SurveyAttempt();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(118);
}

$assess = $assessHandler->getByID($this->arg[0]);
$attempt = $attemptHandler->getByID($this->arg[1]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$attempt) {
    error(118);
}

if (!$assessHandler->isOwner($this->arg[0])) {
    error(127);
}

$attemptHandler->reviewed($this->arg[1]);

localRedir('/survey/results/' . $this->arg[0]);

die();
?>
