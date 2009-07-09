<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class TestQuestion extends Model {
	function TestQuestion () {
		$this->_table = "test_question";
		$this->_index = "id";
		
		// default data
		$this->data = $this->_default = array(
			'id'       => '',
			'test_id'  => '',
			'question' => '',
			'type'     => 'textual',
			'mark'     => '1',
			'answer'   => array(
				'text'     => '',
				'keywords' => ''
			)
		);
		
		parent::Model();
	}
	
	// creating questions
	function create($data) {
		if(isset($data['answer']))
			$data['answer'] = serialize($data['answer']);
			
		$this->data = array_merge($this->data, $data);
		$this->data['id'] = parent::create($this->data);

		return $this->data['id'];
	}
	
	// modify questions
	function modify($data) {
		if(isset($data['answer']))
			$data['answer'] = serialize($data['answer']);
			
		$this->data = array_merge($this->data, $data);
		
		return parent::update($data);
	}
	
	// get question by id
	function getByID($id) {
		$data = parent::getByID($id);
		$data['answer'] = unserialize($question['answer']);
		
		$this->data = $data;
		
		return $this->data;
	}
	
	// get all question by a field
	function getAllByField($field, $value) {
		$data = parent::getAllByField($field, $value);
		
		if(empty($data))
			$data = $this->_default;
		
		foreach($data as $i => $question)
			$data[$i]['answer'] = unserialize($question['answer']);
		
		$this->data = $data;
		
		return $this->data;
	}
}

?>