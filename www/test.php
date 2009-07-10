<?php

// require librarys
require_once("../library/group.php");
require_once("../library/test.php");
require_once("../library/test-question.php");
require_once("../library/test-attempt.php");
require_once("../library/test-response.php");

// models
$group    = new Group();
$test     = new Test();
$question = new TestQuestion();
$attempt  = new TestAttempt();
$response = new TestResponse();

// get test
$data = $_POST;
$test->getByID($this->arg[0]);

// common template vars
$this->setTitle($test->data['title']);
$this->setVar('TEST_TITLE', $test->data['title']);

if(empty($data)) {
	// get template
	$this->setFile(TEMPLATE_content, 'test/cover.html');
	
	// output for cover
	$this->setTitle($test->data['title']);
	$this->setVar(array(
		'DATE_START'       => $test->data['date']['start'],
		'DATE_FINISH'      => $test->data['date']['finish'],
		'DURATION_HOURS'   => $test->data['duration']['hours'],
		'DURATION_MINUTES' => $test->data['duration']['minutes'],
		'DESCRIPTION'      => $test->data['description']
	));
} elseif($data['action'] == 'start') {
	// get template
	$this->setFile(TEMPLATE_content, 'test/test.html');
	
	// record attempt if not already
	$attempt->getByArray(array(
		'test_id' => $test->data['id'],
		'user_id' => $_SESSION['id']
	));
	
	if(empty($attempt->data['id']))
		$attempt->create(array(
			'test_id' => $test->data['id'],
			'user_id' => $_SESSION['id']
		));
	
	// attempt id
	$this->setTitle($test->data['title']);
	$this->setVar(array(
		'ATTEMPT_ID'       => $attempt->data['id'],
		'DURATION_HOURS'   => $test->data['duration']['hours'],
		'DURATION_MINUTES' => $test->data['duration']['minutes']		
	));
	
	// questions
	$question->getAllByField('test_id', $test->data['id']);
	
	$this->setBlock(TEMPLATE_content, 'list', 'list_block');
	
	foreach($question->data as $question) {
		$this->setFile('question', "test/questions/response/{$question['type']}.html");
		$this->setVar(array(
			'QUESTION_ID'       => $question['id'],
			'QUESTION_QUESTION' => $question['question'],
		));
		
		$this->parse('list_block', 'list', true);
		$this->parse('QUESTION', 'question', true);
	}
} elseif($data['action'] == 'finish') {
	// get template
	$this->setFile(TEMPLATE_content, 'test/result.html');

	// finish attempt
	$attempt->modify(array(
		'id'     => $data['attempt_id'],
		'finish' => date('d/m/Y'),
		'status' => 'complete'
	));
	
	// create questions
	foreach($data['response'] as $resp) {
		$response->create(array(
			'attempt_id'  => $attempt->data['id'],
			'question_id' => $resp['question_id'],
			'response'    => $resp['response']
		));
	}
}

?>