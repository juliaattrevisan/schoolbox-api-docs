<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

require_once(dirname(__FILE__) . '/../www/conf/conf.php');
/**
 * @deprecated
 * Class DeprecatedModel
 */
class DeprecatedModel
{
    protected $_table;
    protected $_index;

    /**
     * A database connection
     */
    protected IConnector $db;

    /**
     * @deprecated
     */
    public function __construct()
    {
        $this->db = Factory::getInstanceInternal();
    }

    /**
     * @deprecated
     * @param mixed $id
     * @return array|bool|null
     */
    public function getByID($id)
    {
        $quoter = $this->db->getQuoter();
        $query  = "
            SELECT * FROM {$quoter->quoteIdentifier($this->_table)}
            WHERE {$quoter->quoteIdentifier($this->_index)} = ?
        ";
        return $this->db->query($query, [$id])->fetch();
    }

    /**
     * @deprecated
     * @param string $field
     * @param mixed  $id
     * @return array|bool|null
     */
    public function getByField($field, $id)
    {
        $quoter = $this->db->getQuoter();
        $query  = "
            SELECT * FROM {$quoter->quoteIdentifier($this->_table)}
            WHERE {$quoter->quoteIdentifier($field)} = ?
        ";
        return $this->db->query($query, [$id])->fetch();
    }

    /**
     * @deprecated
     * @param mixed $fieldList
     * @param string $termOp
     * @return array|bool
     */
    public function getByArray($fieldList, $termOp = 'AND')
    {
        $quoter = $this->db->getQuoter();
        $query  = "SELECT * FROM {$quoter->quoteIdentifier($this->_table)} WHERE ";
        $params = [];
        if (is_array($fieldList)) {
            if ($termOp == 'AND') {
                $query .= '1 = 1';
            } else {
                $query .= '1 = 0';
            }

            foreach ($fieldList as $field => $value) {
                $query .= " $termOp {$quoter->quoteIdentifier($field)} = ?";
                $params[] = $value;
            }

            return $this->db->query($query, $params)->fetchAll();
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
        return $this->db->lastInsertId();
    }
}
