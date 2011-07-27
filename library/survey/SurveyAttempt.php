<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationAttempt.php');

class SurveyAttempt extends AssessAttempt {
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';
}

?>
