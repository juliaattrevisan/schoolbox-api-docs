<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

require_once(dirname(__FILE__) . '/../www/conf/conf.php');
/**
 * @deprecated
 * Class DeprecatedModel
 */
class DeprecatedModel
{

    var $_table;
    var $_index;
/** @var DeprecatedDatabase */
    var $db;
/**
     * @deprecated
     */
    public function __construct()
    {
        $this->db = DeprecatedDatabase::getInstance();
    }

    /**
     * @deprecated
     * @param $id
     * @return array|bool|null
     */
    public function getByID($id)
    {
        $query = "	SELECT *
					FROM `" . $this->_table . "` WHERE `" . $this->_index . "` =  " . $this->db->smartQuote($id);
        return $this->db->getRow($query);
    }

    /**
     * @deprecated
     * @param $field
     * @param $id
     * @return array|bool|null
     */
    public function getByField($field, $id)
    {
        $query = "	SELECT *
					FROM `" . $this->_table . "` WHERE `" . $field . "` = " . $this->db->smartQuote($id);
        return $this->db->getRow($query);
    }

    /**
     * @deprecated
     * @param $fieldList
     * @param string $termOp
     * @return array|bool
     */
    public function getByArray($fieldList, $termOp = 'AND')
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

    /**
     * @deprecated
     * @param $field
     * @param $id
     * @param string $orderBy
     * @param string $orderDir
     * @return array|bool
     */
    public function getAllByField($field, $id, $orderBy = "", $orderDir = "")
    {
        $query = "	SELECT *
					FROM `" . $this->_table . "` WHERE `" . $field . "` = " . $this->db->smartQuote($id);
        if ($orderBy != "") {
            $query .= " ORDER BY $orderBy $orderDir";
        }

        return $this->db->getAll($query);
    }

    /**
     * @deprecated
     * @param string $order
     * @return array|bool
     */
    public function getAll($order = '')
    {
        $query = "SELECT * FROM `" . $this->_table . "`";
        if ($order) {
            $query .= " ORDER BY `$order`";
        }

        return $this->db->getAll($query);
    }

    /**
     * @deprecated
     * @param $name
     * @return mixed
     */
    public function getIDByName($name)
    {
        $query = "SELECT `" . $this->_index . "` FROM `" . $this->_table . "` WHERE name = '$name'";
        $row = $this->db->getRow($query);
        return $row[$this->_index];
    }

    /**
     * Get all the rows matching multiple key/value pairs
     *
     * @deprecated
     * @param <type> $fields
     * @return <type>
     */
    public function getAllByFields($fields, $type = 'AND')
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

    /**
     * @deprecated
     * @param $object
     * @return bool
     */
    public function update($object)
    {
        return $this->db->update($this->_table, $this->_index, $object);
    }

    /**
     * @deprecated
     * @param $id
     * @return bool
     */
    public function delete($id)
    {
        return $this->db->delete($this->_table, $this->_index, $id);
    }

    /**
     * @deprecated
     * @param $object
     * @return int|string
     */
    public function create($object)
    {
        $this->db->insert($this->_table, $object);
        return $this->db->insertID();
    }
}
