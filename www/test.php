<?php

// require librarys
require_once("../library/group.php");
require_once("../library/test.php");
require_once("../library/test-question.php");
require_once("../library/test-attempt.php");

// models
$group    = new Group();
$test     = new Test();
$attempt  = new TestAttempt();

// get test
$data = $_POST;
$test->getByID($this->arg[0]);

if(empty($data)) {
	// get template
	$this->setFile(TEMPLATE_content, 'test/cover.html');
	
	// output for cover
	$this->setTitle($test->data['title']);
	$this->setVar(array(
		'TEST_TITLE'       => $test->data['title'],
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
	
	// output
	$this->setTitle($test->data['title']);
	$this->setVar(array(
		'TEST_ID'    => $test->data['id'],
		'ATTEMPT_ID' => $attempt->data['id'],
		'TEST_TITLE' => $test->data['title']
	));
} elseif($data['action'] == 'finish') {
	
}

?>