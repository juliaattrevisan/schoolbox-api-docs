<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess-attempt.php';

$attemptHandler = new AssessAttempt();

$attemptHandler->reviewed($this->arg[1]);

localRedir('/submission/listSubmission');
?>