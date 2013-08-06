<?php

/************************************************************
 *                                                           *
 *         Copyright (c) 2006 Lektec Consulting                *
 *                                                           *
 ************************************************************/

require_once(dirname(__FILE__) . '/../www/conf/conf.php');

class DeprecatedModel
{

    var $_table;

    var $_index;

    function DeprecatedModel()
    {
        $this->db = DeprecatedDatabase::getInstance();
    }

    function getByID($id)
    {
        $query = "	SELECT *
					FROM `" . $this->_table . "` WHERE `" . $this->_index . "` =  " . $this->db->smartQuote($id);
        return $this->db->getRow($query);
    }

    function getByField($field, $id)
    {
        $query = "	SELECT *
					FROM `" . $this->_table . "` WHERE `" . $field . "` = " . $this->db->smartQuote($id);
        return $this->db->getRow($query);
    }

    function getByArray($fieldList, $termOp = 'AND')
    {
        $query = "SELECT * FROM `" . $this->_table . "` WHERE ";

        if (is_array($fieldList)) {
            if ($termOp == 'AND') {
                $query .= '1 = 1';
            } else {
                $query .= '1 = 0';
            }

            foreach ($fieldList as $field => $value) {
                if (is_numeric($value)) {
                    $query .= " $termOp `$field` = " . $this->db->smartQuote($value);
                } else {
                    $query .= " $termOp `$field` like " . $this->db->smartQuote($value);
                }
            }

            return $this->db->getAll($query);
        } else {
            return false;
        }
    }

    function getAllByField($field, $id, $orderBy = "", $orderDir = "")
    {
        $query = "	SELECT *
					FROM `" . $this->_table . "` WHERE `" . $field . "` = " . $this->db->smartQuote($id);
        if ($orderBy != "")
            $query .= " ORDER BY $orderBy $orderDir";

        return $this->db->getAll($query);
    }

    function getAll($order = '')
    {
        $query = "SELECT * FROM `" . $this->_table . "`";
        if ($order) {
            $query .= " ORDER BY `$order`";
        }

        return $this->db->getAll($query);
    }

    function getIDByName($name)
    {
        $query = "SELECT `" . $this->_index . "` FROM `" . $this->_table . "` WHERE name = '$name'";
        $row = $this->db->getRow($query);
        return $row[$this->_index];
    }

    /**
     * Get all the rows matching multiple key/value pairs
     *
     * @param <type> $fields
     * @return <type>
     */
    function getAllByFields($fields, $type = 'AND')
    {
        if (is_array($fields)) {
            $query = "SELECT *
					  FROM {$this->_table}
					  WHERE ";

            foreach ($fields as $key => $value) {
                $subquery[] = "$key = \"$value\" ";
            }

            $query .= implode(" $type ", $subquery);

            return $this->db->getAll($query);
        }

        return false;
    }

    function update($object)
    {
        return $this->db->update($this->_table, $this->_index, $object);
    }

    function delete($id)
    {
        return $this->db->delete($this->_table, $this->_index, $id);
    }

    function create($object)
    {
        $this->db->insert($this->_table, $object);
        return $this->db->insertID();
    }
}