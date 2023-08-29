<?php

class TextBox extends DeprecatedModel implements \Schoolbox\IHomepageCleanable
{
    public function __construct()
    {
        $this->_table = 'textBox';
        $this->_index = 'textBoxID';

        parent::__construct();
    }

    /**
     * Soft-deletes content
     *
     * TODO: Refactor
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid)
    {
        $query = "UPDATE {$this->_table} SET deleted_at = NOW() WHERE categoryID = '{$cid}'";

        $this->db->query($query);
    }
}
