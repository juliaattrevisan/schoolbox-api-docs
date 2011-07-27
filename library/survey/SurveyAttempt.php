<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationAttempt.php');

class SurveyAttempt extends AssessAttempt {
    protected $_table = 'assess_attempt';
    protected $_index = 'attempt_id';
}

?>
