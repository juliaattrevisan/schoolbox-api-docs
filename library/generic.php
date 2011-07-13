<?php

class GenericTable extends DeprecatedModel {

    function __construct($table, $index) {
        $this->_table = $table;
        $this->_index = $index;
        parent::DeprecatedModel();
    }
}
?>