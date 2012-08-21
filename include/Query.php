<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Query {

    private $query = array(
        'SELECT' => array(),
        'FROM'   => array(),
        'JOIN'   => array(),
        'WHERE'  => array(),
        'GROUP'  => array(),
        // HAVING intentionally omitted, can put it in if it proves necessary
        'ORDER'  => array(),
        'LIMIT'  => array(),
    );

    public function select($field, $as = '') {

        // if there is a bracket, it will be a function of some kind so don't add backticks
        if(strpos($field, '(') !== false) {
            $select = "$field" . ($as ? " as $as" : '');
        }
        elseif($field == '*') {
            $select = $field;
        }
        else {
            $field = str_replace('.','`.`',$field);
            $select = "`$field`" . ($as ? " as $as" : '');
        }
        $this->query['SELECT'][] = $select;
    }

    public function from($table) {
        $this->query['FROM'][] = "`$table`";
    }

    public function join($direction, $table, $onFrom, $onTo) {
        $join = "$direction JOIN `$table` ON $onFrom = $onTo";
        $this->query['JOIN'][] = $join;
    }

    public function where($field, $how, $value, $logic = 'AND') {
        $field = str_replace('.','`.`',$field);

        // backtick indicates this is a field, not a literal
        $where = "`$field` $how ";

        if(is_array($value)) {
            $vals = implode("','", $value);
            $where = "`$field` IN ('$vals')";
        }

        elseif(strpos($value, '`') !== false) {
            $where .= $value;
        }

        else {
            $where .= "'$value'";
        }

        $this->query['WHERE'][] = array($logic, $where);
    }

    public function group($field) {
        $field = str_replace('.', '`.`', $field);
        $this->query['GROUP'][] = "`$field`";
    }

    public function order($field, $dir = 'ASC') {
        $field = str_replace('.', '`.`', $field);
        $this->query['ORDER'][] = "`$field` $dir";
    }

    public function limit($offset, $limit) {
        $this->query['LIMIT'][] = "$offset, $limit";
    }

    /**
     * Use sparingly...
     * @param $clause
     */
    public function clear($clause = '') {

        $clause = strtoupper($clause);

        if($clause == '') {
            foreach(array_keys($this->query) as $key) {
                if($key == 'FROM')
                    continue;
                $this->query[$key] = array();
            }
        }
        elseif(isset($this->query[$clause])) {
            $this->query[$clause] = array();
        }
    }

    public function __toString() {
        $query[] = "SELECT " . implode(', ', $this->query['SELECT']);
        $query[] = "FROM " . implode(', ', $this->query['FROM']);

        if(count($this->query['JOIN'])) {
            foreach($this->query['JOIN'] as $j)
            $query[] = $j;
        }

        if(count($this->query['WHERE'])) {
            for($i = 0; $i < count($this->query['WHERE']); $i++) {
                $logic = $i == 0 ? 'WHERE' : $this->query['WHERE'][$i][0];
                $query[] = "$logic " . $this->query['WHERE'][$i][1];
            }
        }

        if(count($this->query['GROUP'])) {
            $query[] = "GROUP BY " . implode(', ', $this->query['GROUP']);
        }

        if(count($this->query['ORDER'])) {
            $query[] = "ORDER BY " . implode(', ', $this->query['ORDER']);
        }

        if(count($this->query['LIMIT'])) {
            $query[] = "LIMIT " . $this->query['LIMIT'][0];
        }

        $string = implode("\n", $query);

        return $string;
    }
}

