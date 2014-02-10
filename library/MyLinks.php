<?php

class MyLinks extends DeprecatedModel {

	public function MyLinks() {
		$this->_table = 'bookmarks';
		$this->_index = 'id';

		parent::DeprecatedModel();
	}

    function linkExists($uid, $link) {
        $query = "SELECT id FROM bookmarks WHERE owner='$uid' AND url = '".$this->db->quote($link)."'";
        return $this->db->getOne($query);
    }
}
