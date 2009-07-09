<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class Test extends Model {
	function Test () {
		$this->_table = "test";
		$this->_index = "id";
		
		// default data
		$this->data = $this->_default = array(
			'id'		  => '',
			'category_id' => '',
			'title'       => '',
			'description' => '',
			'date'        => array(
				'start'       => date('d/m/Y'),
				'finish'      => date('d/m/Y', strtotime('+1 week'))
			),
			'duration'    => array(
				'hours'   => 1,
				'minutes' => 30
			)
		);
		
		parent::Model();
	}
	
	// creating tests
	function create($data) {
		$this->data = array(
			'category_id' => $data['category_id'],
			'title'       => $data['title'],
			'description' => $data['description'],
			'start'       => date('Y/m/d', strtotime($data['date']['start'])),
			'finish'      => date('Y/m/d', strtotime($data['date']['finish'])),
			'duration'    => ($data['duration']['hours'] * 60) + $data['duration']['minutes']
		);
		
		$this->data['id'] = parent::create($this->data);
		
		return $this->data;
	}
	
	// update tests
	function modify($data) {
		$this->data = array(
			'id'          => $data['id'],
			'category_id' => $data['category_id'],
			'title'       => $data['title'],
			'description' => $data['description'],
			'start'       => date('Y/m/d', strtotime($data['date']['start'])),
			'finish'      => date('Y/m/d', strtotime($data['date']['finish'])),
			'duration'    => ($data['duration']['hours'] * 60) + $data['duration']['minutes']
		);
		
		return parent::update($this->data);
	}
	
	// find test by id
	function getByID($id) {
		$data = parent::getByID($id);
		
		// format dates
		$data['date'] = array(
			'start'  => date('d/m/Y', strtotime($data['start'])),
			'finish' => date('d/m/Y', strtotime($data['finish']))
		);
		
		// format duartion
		$data['duration'] = array(
			'hours'   => ($data['duration'] - ($data['duration'] % 60)) / 60,
			'minutes' => $data['duration'] % 60
		);
		
		$this->data = $data;
		
		return $this->data;
	}
}

?>