<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');

$questionHandler = new AssessQuestion();
$question = $questionHandler->getByID($this->arg[0]);

$factory = new AssessFactory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$answerHandler->saveResponse($_POST, $this->arg[0]);

?>