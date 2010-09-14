<?php
require_once('../library/assess/assess.php');
require_once('../library/assess/assess-factory.php');
require_once('../library/assess/assess-section.php');

$questionHandler = new AssessQuestion();
$question = $questionHandler->getByID($this->arg[0]);

$factory = new AssessFactory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$data = $_POST;
$data['section_id'] = $question['section_id'];
$data['question_id'] = $this->arg[0];

$result = $answerHandler->saveResponse($data);

/* Find out how many questions were completed in this section already
 */
$sectionHandler = new AssessSection();
$result['percentageComplete'] = $sectionHandler->sectionPercentageComplete($question['section_id']);

echo json_encode($result);

/* Prevent rendering of template
 */
exit();

?>