<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class TestAttempt extends Model {
	function TestAttempt () {
		$this->_table = "test_attempt";
		$this->_index = "id";
		
		// default data
		$this->data = $this->_default = array(
			'id'      => '',
			'test_id' => '',
			'user_id' => '',
			'status'  => 'incomplete',
			'start' => date('Y/m/d H:i:s'),
			'finish'=> ''
		);
		
		parent::Model();
	}
	
	// creating a test attempt
	function create($data) {
		// format dates
		if(!empty($data['start']))
			$data['start'] = date('Y/m/d H:i:s', strtotime($data['start']));
		if(!empty($data['finish']))
			$data['finish'] = date('Y/m/d H:i:s', strtotime($data['finish']));

		$this->data = array_merge($this->data, $data);
		$this->data['id'] = parent::create($this->data);
		
		return $this->data;
	}
	
	// modify
	function modify($data) {
		// format dates
		if(!empty($data['start']))
			$data['start'] = date('Y/m/d H:i:s', strtotime($data['start']));
		if(!empty($data['finish']))
			$data['finish'] = date('Y/m/d H:i:s', strtotime($data['finish']));
	
		$this->data = array_merge($this->data, $data);
				
		return parent::update($data);
	}
	
	// find attempt by id
	function getByID($id) {
		$data = parent::getByID($id);
		
		// format dates
		$data['start'] = date('d/m/Y', strtotime($data['start']));
		if(!empty($data['finish']))
			$data['finish'] = date('d/m/Y', strtotime($data['finish']));
		
		$this->data = $data;
		
		return $this->data;
	}
	
	// find attempt by field
	function getByArray($data) {
		$data = parent::getByArray($data);
		
		if(empty($data)) {
			$data = $this->_default;
		} else {
			// format dates
			foreach($data as $i => $attempt) {
				$data[$i]['start'] = date('d/m/Y', strtotime($attempt['start']));
				if(!empty($attempt['finish']))
					$data[$i]['finish'] = date('d/m/Y', strtotime($attempt['finish']));
			}	
		}
		
		$this->data =count($data) == 1 ? $data[0] : $data;
		
		return $this->data;
	}
}

?>