<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Generic_DataMapper {

    // implementing mappers will need to override these
    const TABLE = '';
    const INDEX = 'id';

    protected $hydrates = array();
    protected $map = array();
    protected $objClassname;
    protected $query;

    public function __construct() {
        $this->query = new Query();
        $called = get_called_class();
        $this->from($called::TABLE);
        $this->objClassname = substr($called, 0, strpos($called, '_DataMapper'));
    }

    // by default, class members correlate exactly to database fields
    // to override, extending classes override the $map member
    public function map($dbField, $classMember = '') {
        $called = get_called_class();

        if($classMember == '')
            $classMember = $dbField;

        $this->map[$dbField] = $classMember;
    }

    public function create(Generic_Object $obj) {
        $colMap = $this->getColumnList();
    }

    /**
     * @param $value
     * @param int $limit
     * @return GizaCollection
     * @throws Exception_Schoolbox
     */
    public function read($conditions = '', $limit = 0, $clearWhere = true) {
        $called = get_called_class();

        // clear previous read by default
        if($clearWhere) {
            $this->clear('where');
        }

        if(is_array($conditions)) {
            foreach($conditions as $field => $value) {
                if($field == '') {
                    $field = $called::INDEX;
                }

                if(is_array($value) && count($value)) {
                    $this->where($field, 'IN', $value);
                }
                else {
                    $this->where($field, '=', $value);
                }

                $this->order($field);
            }
        }
        elseif($conditions != '') {
            $this->where($called::INDEX, '=', $conditions);
            $this->order($called::INDEX);
        }

        if($limit && is_numeric($limit)) {
            $this->limit(0, $limit);
        }

        $result = DeprecatedDatabase::getInstance()->getAll("{$this->query}");

        if(DEBUG_SQL_ENABLED) {
            printf("-- READ --\n%s\n-- ENDREAD --\n\n!! SQL RESULT !!\n%s\n!! END SQL RESULT !!\n\n",
                $this->query,
                print_r($result, true)
            );
        }

        // check there are results
        if(is_array($result)) {
            if(count($result) == 1) {
                $result = array_merge($result[0], $this->hydrates);
                return new GizaCollection(new $this->objClassname($result));
            }
            else {
                $batch = array();
                foreach($result as $row) {
                    $batch[] = new $this->objClassname(array_merge($row, $this->hydrates));
                }
                return new GizaCollection($batch);
            }

        }
        throw new Exception_Schoolbox('No results returned.');
    }

    public function update(Generic_Object $obj) {

    }

    public function delete(Generic_Object $obj) {

    }

    /**
     * Attempt to fill a field with database data
     * @param $field
     */
    public final function hydrate(Generic_Object $instance, $field, $args = '') {

        $method = 'hydrate' . score2camel($field);

        if(method_exists($this, $method)) {
            try {
                if($args) {
                    $this->$method($instance, $args);
                }
                else {
                    $this->$method($instance);
                }
            }
            catch(ErrorException $e) {
                echo $e->getTraceAsString();
                throw new Exception_Schoolbox("$method() failed to hydrate: ".$e->getMessage());
            }
        }
    }

    protected function setHydrateField($field) {
        $this->hydrates[$field] = new GizaCollection();
    }

    protected function getColumnList() {
        // start with list of database fields
        $colMap = $this->hydrates;

        return $colMap;

    }

    // extending classes can override these methods in order to specify custom database queries
    protected static function manualCreateSQL() { return false; }
    protected static function manualReadSQL()   { return false; }
    protected static function manualUpdateSQL() { return false; }
    protected static function manualDeleteSQL() { return false; }


    // query-encapsulating methods for the convenience of extending classes
    protected function select($field, $as = '') {
        $this->query->select($field, $as);
    }

    protected function from($table) {
        $this->query->from($table);
    }

    protected function join($direction, $table, $onFrom, $onTo) {
        $this->query->join($direction, $table, $onFrom, $onTo);
    }

    /**
     * Convenience method for the sake of Doctrine developers
     * @param $table
     * @param $onFrom
     * @param $onTo
     */
    protected function leftJoin($table, $onFrom, $onTo) {
        $this->query->join('LEFT', $table, $onFrom, $onTo);
    }

    protected function where($field, $how, $value, $logic = 'AND') {
        $this->query->where($field, $how, $value, $logic);
    }
    protected function group($field) {
        $this->query->group($field);
    }
    protected function order($field, $dir = 'ASC') {
        $this->query->order($field, $dir);
    }
    protected function limit($offset, $limit) {
        $this->query->limit($offset, $limit);
    }
    protected function clear($clause = '') {
        $this->query->clear($clause);
    }

}

// text manipulation
// TODO move these to a stdfuncs file
function camel2score($text) {
    $bits = preg_split('/([[:upper:]][[:lower:]]+)/', $text, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
    foreach($bits as &$b) {
        $b = strtolower($b);
    }
    return implode('_', $bits);
}

function score2camel($text) {
    $bits = explode('_', $text);
    foreach($bits as &$b) {
        $b = ucfirst(strtolower($b));
    }
    return implode('', $bits);
}
