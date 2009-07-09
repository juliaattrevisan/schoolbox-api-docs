<?php

// require librarys
require_once("../library/group.php");
require_once("../library/test.php");
require_once("../library/test-question.php");

// models
$group    = new Group;
$test     = new Test();
$question = new TestQuestion();

// submitted?
if(!empty($_POST)) {
	$data = $_POST;
	
	if($data['ajax']) {
		// get template
		$this->setFile(TEMPLATE_body, "test/questions/create/{$data['type']}.html");
		$this->setVar(array(
			'N'    => $data['n'],
			'TYPE' => $data['type']
		));
		
		// marks dropdown
		$this->setBlock(TEMPLATE_body, 'mark', 'mark_block');
		foreach(range(1, 5) as $mark) {
			$this->setVar('MARK', $mark);
			$this->parse('mark_block', 'mark', true); 
		}	
		
		// clear unknowns and output
		$this->setUnknowns('remove');
		$this->outputPage(TRUE);
	} else {
		// create or update	
		if(!$modify)
			$test->create($data);
		else
			$test->modify($data);
		
		// create/update questions
		foreach($data['questions'] as $data) {
			$data['test_id'] = $test->data['id'];
			!$modify || empty($data['id']) ? $question->create($data) : $question->modify($data);
		}
	}

	exit;
}

// template
$this->setFile(TEMPLATE_content, 'test/create.html');
$this->setVar('ACTION', $modify ? 'Modify Test' : 'Create Test');
$this->setUnknowns('remove');

// modify or create?
if(!$modify) {
	$this->setTitle('Create Test');
	$this->setVar('ACTION', 'Create Test');
	
	$data = $test->data;
	$data['category_id'] = $this->arg[0];
	$data['questions'][] = $question->data;
} else {
	$this->setTitle('Modify Test');
	$this->setVar('ACTION', 'Modify Test');
	
	$data = $test->getByID($this->arg[0]);
	$data['questions'] = $question->getAllByField('test_id', $this->arg[0]);
}

$this->setVar(array(
	'COUNT'       => count($data['questions']),
	'TEST_ID'     => $data['id'],
	'TEST_TITLE'  => $data['title'],
	'DATE_START'  => $data['date']['start'],
	'DATE_FINISH' => $data['date']['finish'],
	'DESCRIPTION' => $data['description']
));

// category list
$this->setBlock(TEMPLATE_content, 'category', 'category_block');
foreach($group->involvedCategoryList($_SESSION['id'], null, WRITE_ACCESS) as $category) {
	$this->setVar('CATEGORY_ID', $category['id']);
	$this->setVar('CATEGORY_NAME', $category['name']);
	
	if($data['category_id'] == $category['id'])
		$this->setVar('CATEGORY_ID_SELECTED', 'selected="selected"');
	else
		$this->setVar('CATEGORY_ID_SELECTED', '');
		
	$this->parse('category_block', 'category', true); 
}

// hours and minutes dropdowns
$this->setBlock(TEMPLATE_content, 'duration_hours', 'duration_hours_block');
foreach(range(1, 5) as $hour) {
	$this->setVar('HOUR', $hour);
	
	if($data['duration']['hours'] == $hour)
		$this->setVar('HOUR_SELECTED', 'selected="selected"');
	else
		$this->setVar('HOUR_SELECTED', '');
		
	$this->parse('duration_hours_block', 'duration_hours', true); 
}

$this->setBlock(TEMPLATE_content, 'duration_minutes', 'duration_minutes_block');
foreach(range(0, 45, 15) as $minute) {
	$this->setVar('MINUTE', $minute);
	
	if($data['duration']['minutes'] == $minute)
		$this->setVar('MINUTE_SELECTED', 'selected="selected"');
	else
		$this->setVar('MINUTE_SELECTED', '');
		
	$this->parse('duration_minutes_block', 'duration_minutes', true); 
}

// questions
foreach($data['questions'] as $n => $question) {
	$this->setFile('question', "test/questions/create/{$question['type']}.html");
	$this->setVar(array(
		'QUESTION_ID' => $question['id'],
		'N'           => $n,
		'QUESTION'    => $question['question'],
		'TYPE'        => $question['type']
	));
	
	// answer
	foreach($question['answer'] as $key => $value)
		$this->setVar('ANSWER_' . strtoupper($key), $value);
	
	// marks
	$this->setBlock('question', 'mark', 'mark_block');
	foreach(range(1, 5) as $mark) {
		$this->setVar('MARK', $mark);
		
		if($question['mark'] == $mark)
			$this->setVar('MARK_SELECTED', 'selected="selected"');
		else
			$this->setVar('MARK_SELECTED', '');
			
		$this->parse('mark_block', 'mark', true); 
	}	
	
	$this->parse('QUESTIONS', 'question', true);
}

?>