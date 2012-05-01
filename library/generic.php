<?php

class GenericTable extends DeprecatedModel {

    function __construct($table, $index) {
        $this->_table = $table;
        $this->_index = $index;
        parent::DeprecatedModel();
    }

    function tableExists() {
        $query = "SHOW TABLES FROM `" . SQL_db . "` LIKE '{$this->_table}'";
        if($this->db->getRow($query))
            return true;
        return false;
    }

    function getDefinition() {
        $query = "SHOW COLUMNS FROM `$this->_table`";
        return $this->db->getAll($query);
    }

    function getLimitedDefinition($visibleFields) {
        if(!is_array($visibleFields))
            return $this->getDefinition();

        $fieldString = implode("', '", $visibleFields);

        $query = "SHOW COLUMNS FROM `$this->_table` WHERE `Field` IN ('$fieldString')";
        return $this->db->getAll($query);
    }

    function getAllInFields($fields) {
        if(!is_array($fields))
            return $this->getAll();

        $fieldString = implode('`, `', $fields);
        $query = "SELECT `$fieldString` FROM `$this->_table`";

        return $this->db->getAll($query);
    }
}
?>