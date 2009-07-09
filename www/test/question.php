<?php

// require librarys
require_once("../library/group.php");
require_once("../library/test.php");
require_once("../library/test-attempt.php");
require_once("../library/test-question.php");
require_once("../library/test-response.php");

// models
$group    = new Group();
$question = new TestQuestion();
$attempt  = new TestAttempt();
$response = new TestResponse();

$data = $_POST;

switch($data['action']) {
	// provide initial list of questions
	case 'question':
		$question->getAllByField('test_id', $data['test_id']);
		
		echo json_encode($question->data);
	break;
	
	// template individual question
	case 'response':
		$next = $data['next']; unset($data['next'], $data['action']);
	
		// create/update response
		$response->getByArray(array(
			'attempt_id' => $data['attempt_id'],
			'question_id' => $data['question_id']
		));
		
		if(empty($response->data['attempt_id'])) {
			$response->create($data);
		} else {
			$response->modify($data);
		}
		
		// get next question
		$question->getByID($next);
		
		$this->setFile(TEMPLATE_body, "test/questions/response/{$question->data['type']}.html");
		$this->setVar(array(
			'QUESTION'    => $question->data['question'],
			'ATTEMPT_ID'  => $data['attempt_id'],
			'QUESTION_ID' => $question->data['id']			
		));
		
		// any existing repsonses
		$response->getByArray(array(
			'attempt_id' => $data['attempt_id'],
			'question_id' => $question->data['id']
		));
		
		if(!empty($response->data['attempt_id']))
			$this->setVar('RESPONSE', $response->data['response']);
		
		// clear any unused variables and output
		$this->setUnknowns('remove');
		$this->outputPage(TRUE);
	break;
}

exit();

?>