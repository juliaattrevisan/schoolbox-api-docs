<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationAttempt.php');

class SurveyAttempt extends EvaluationAttempt {
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';
}

?>
