<?php

require_once dirname(__FILE__) . '/../include/model.php';

class GenericTable extends Model {

    function __construct($table, $index) {
        $this->_table = $table;
        $this->_index = $index;
        parent::Model();
    }

    function getTable() {
        return $this->db->getAll('SELECT * FROM '.$this->_table);
    }
}
?>