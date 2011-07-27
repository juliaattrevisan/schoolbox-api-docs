<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/Evaluation.php');

class Survey extends Evaluation {
    const ONLINE_ASSESSMENT_TYPE_ID = 11;
    const UNLIMITED_DURATION = -1;
}
?>
