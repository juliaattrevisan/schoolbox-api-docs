<?php

class MyLinks extends DeprecatedModel
{

    public function __construct()
    {
        $this->_table = 'bookmarks';
        $this->_index = 'id';

        parent::__construct();
    }

    function linkExists($uid, $link)
    {
        $query = "SELECT id FROM bookmarks WHERE owner='$uid' AND url = '" . $this->db->quote($link) . "'";
        return $this->db->getOne($query);
    }
}
