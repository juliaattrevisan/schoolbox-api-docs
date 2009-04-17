<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class TextBox extends Model {

	function TextBox () {
		$this->_table = 'textBox';
		$this->_index = 'textBoxID';
		
		parent::Model();
	}
	
}
?>