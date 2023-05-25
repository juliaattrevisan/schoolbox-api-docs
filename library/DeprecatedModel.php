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
    protected $_table;
    protected $_index;

    /** @var Database */
    protected Database $db;
    /**
     * @deprecated
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @deprecated
     * @param mixed $id
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
     * @param string $field
     * @param mixed  $id
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
     * @param mixed $fieldList
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
     * @param string $order
     * @return array|bool
     */
    public function getAll($order = '')
    {
        $quoter = $this->db->getQuoter();
        $query  = "SELECT * FROM {$quoter->quoteIdentifier($this->_table)}";
        if ($order) {
            $query .= " ORDER BY {$quoter->quoteIdentifier($order)}";
        }

        return $this->db->query($query)->fetchAll();
    }

    /**
     * @deprecated
     * @param mixed $object
     * @return bool
     */
    public function update($object)
    {
        return $this->db->update($this->_table, $this->_index, $object);
    }

    /**
     * @deprecated
     * @param mixed $id
     * @return bool
     */
    public function delete($id)
    {
        $quoter = $this->db->getQuoter();
        $this->db->query(
            "
                DELETE FROM {$quoter->quoteIdentifier($this->_table)}
                WHERE {$quoter->quoteIdentifier($this->_index)} = ?
            ",
            [$id],
        );
        return true;
    }

    /**
     * @deprecated
     * @param mixed $object
     * @return int|string
     */
    public function create($object)
    {
        $this->db->insert($this->_table, $object);
        return $this->db->insertID();
    }
}
