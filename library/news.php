<?php

require_once '../include/model.php';

class News extends Model {

	function News () {
	
		$this->_table = "news_items";
		$this->_index = "item_id";
		
		parent::Model();
	}
}

?>