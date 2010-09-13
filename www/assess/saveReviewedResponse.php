<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-section.php');

$questionHandler = new AssessQuestion();
$question = $questionHandler->getByID($this->arg[0]);

$factory = new AssessFactory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$result = $answerHandler->saveReviewedResponse($_POST, $this->arg[1]);

echo json_encode($result);

/* Prevent rendering of template
 */
exit();

?>