<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class TestResponse extends Model {
	function TestResponse () {
		$this->_table = "test_response";
		$this->_index = array("attempt_id", "question_id");
		
		// default data
		$this->data = $this->_default = array(
			'attempt_id'  => '',
			'question_id' => '',
			'response'    => ''
		);
		
		parent::Model();
	}
	
	// respond
	function create($data) {
		if(isset($data['response']))
			$data['response'] = serialize($data['response']);
			
		$this->data = array_merge($this->data, $data);
		$this->data['id'] = parent::create($this->data);

		return $this->data['id'];
	}
	
	// modify questions
	function modify($data) {
		if(isset($data['response']))
			$data['response'] = serialize($data['response']);
			
		$this->data = array_merge($this->data, $data);
		
		return parent::update($data);
	}
	
	// get all responses by field
	function getByArray($data) {
		$data = parent::getByArray($data);
		
		if(empty($data)) {
			$data = $this->_default;
		} else {
			foreach($data as $i => $question)
				$data[$i]['response'] = unserialize($question['response']);
		}
		
		$this->data = count($data) == 1 ? $data[0] : $data;
		
		return $this->data;
	}
}

?>