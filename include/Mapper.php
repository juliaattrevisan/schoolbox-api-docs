<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Mapper {
    /**
     * Represents a boolean data type.
     */
    const TYPE_BOOLEAN = 5;

    /**
     * Represents the SQL NULL data type.
     */
    const TYPE_NULL = 0;

    /**
     * Represents the SQL INTEGER data type.
     */
    const TYPE_INTEGER = 1;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     */
    const TYPE_STRING = 2;

    /**
     * Represents the SQL large object data type.
     */
    const TYPE_LOB = 3;

    protected $database;

    protected $table;

    protected $model;

    protected $map;

    protected $identifier;

    /**
     * Stores the singleton instance of the mapper
     *
     * @var Mapper
     */
    static protected $instance;

    /**
     * Retreives the singleton instance
     *
     * @static
     * @return Mapper
     */
    static public function getInstance () {
        if ( !isset($instance) ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    protected function __construct () {
        $this->database = Database::getInstance();
        

        $this->mapping();
    }

    /**
     * Retreives a model by id
     *
     * @param Mixed $identifierValue
     * @return Model
     */
    public function findById ($identifierValue) {
        $identifierName = $this->identifier['column'];

        $statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `{$identifierName}` = :identifierValue
        ");

        $statement->bindParam(':identifierValue', $identifierValue);

        $rowset = $statement->execute()->fetchAll();

        return $this->hydrate($rowset);
    }

    /**
     * Returns a collection with models filtered by column/property
     *
     * @static
     * @param String $columnName
     * @param String $columnValue
     * @return Collection
     */
    public function findByColumn ($columnName, $columnValue) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `{$columnName}` = :$columnName
        ");

        $statement->bindParam(":$columnName", $columnValue);

        $rowset = $statement->execute()->fetchAll();

        return $this->hydrate($rowset);
    }

    public function save ($object) {
        if ( is_subclass_of($object, 'Model') ) {
            $collection = new Collection;
            $collection[] = $object;
        }
        else {
            $collection = $object;
        }

        $statement = $this->database->prepare("
            INSERT INTO `{$this->table}` ({$this->getColumnString()})
            VALUES ({$this->getPlaceholderString()})
            ON DUPLICATE KEY UPDATE {$this->getUpdateString()};
        ");

        foreach ( $collection as $model ) {
            foreach ( $this->map as $map ) {
                $propertyName = $map['property'];
                $columnName = $map['column'];

                $statement->bindParam(":$columnName", $model->$propertyName);
            }

            $statement->execute();
        }

        return true;
    }

    protected function mapping () {
        if ( empty($this->model) ) {
            $modelName = substr( get_class($this), 0, -6 );

            if ( class_exists($modelName, true) ) {
                $this->model = $modelName;
            }
            else {
                return false;
            }

        }

        if ( empty($this->table) ) {
            $this->table = $this->toUnderscore($this->model);
        }

        if ( empty($this->map) ) {
            $propertyList = array_keys( get_class_vars($this->model) );

            if ( !empty($propertyList) ) {

                foreach ( $propertyList as $propertyName ) {

                    $this->hasMap(array(
                        'column'     => $this->toUnderscore($propertyName),
                        'property'   => $propertyName,
                        'identifier' => $propertyName == 'id' ? true : false
                    ));
                    
                }
                
            }
            else {
                return false;
            }
        }

        return true;
    }

    protected function hasTable ($table) {
        if ( !is_string($table) ) {
            return false;
        }

        $this->table = $table;

        return true;
    }

    protected function hasModel ($model) {
        if ( !is_string($model) || !class_exists($model, true) ) {
            return false;
        }

        $this->model = $model;

        return true;
    }

    protected function hasMap ($map) {
        if ( !is_array($map) ) {
            return false;
        }

        if ( !empty($map['identifier']) ) {
            $this->identifier = $map;
        }

        $this->map[] = $map;

        return true;
    }

    /**
     * @param Array $rowset
     * @param String $alias
     * @param Bool $forceCollection
     * @return Collection|Model
     */
    public function hydrate ($rowset, $alias = '', $callback = null) {
        $modelList = array();

        foreach ( $rowset as $row ) {
            $model = new $this->model;

            foreach ( $this->map as $map) {
                $propertyName = $map['property'];
                $columnName = $map['column'];

                if ( !empty($alias) ) {
                    $columnName = "$alias.$columnName";
                }

                $model->$propertyName = $row[$columnName];
            }

            if ( !empty($callback) ) {
                $model = $callback($model);
            }

            $modelList[] = $model;
        }

        return new Collection($modelList);
    }

    public function getColumnString ($alias = '') {

        $columnList = array();

        foreach ( $this->map as $map) {

            if ( !empty($alias) ) {
                $columnString = "`$alias`.`{$map['column']}` AS `$alias.{$map['column']}`";
            }
            else {
                $columnString = "`$this->table`.`{$map['column']}`";
            }

            $columnList[] = $columnString;
        }

        return implode(', ', $columnList);
    }

    public function getPlaceholderString () {
        $placeholderList = array();

        foreach ( $this->map as $map) {
            $placeholderList[] = ":{$map['column']}";
        }

        return implode(', ', $placeholderList);
    }

    public function getUpdateString () {
        $assignmentList = array();

        foreach ( $this->map as $map) {
            $assignmentList[] = "`{$this->table}`.`{$map['column']}` = :{$map['column']}";
        }

        return implode(', ', $assignmentList);
    }

    protected function toCamelcase ($string) {
        $segmentList = explode('_', $string);

        foreach ($segmentList as &$segment) {
            $segment = ucfirst($segment);
        }

        return implode($segmentList);
    }

    public function toUnderscore ($string) {
        $string = str_replace('_', '', $string);

        $segmentList = preg_split('/(?=[A-Z])/', $string, -1, PREG_SPLIT_NO_EMPTY);

        return strtolower( implode( '_', $segmentList) );
    }

}
/*
class User {
    public $id;

    public $username;

    public $password;

    public $groupId;

    public $title;

    public $firstname;

    public $lastname;

    public $emailPass;

    public $synergyId;

    public $campus;

    public $year;

    public $house;

    public $localEmail;

    public $altEmail;

    public $mailHandler;

    public $hash;

    public $key;

    public $eportfolioEnabled;
}

class UserMapper extends Mapper {
    protected function mapping () {

        $this->hasTable('user');
        $this->hasModel('User');

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));
        
        $this->hasMap(array(
            'column'     => 'username',
            'property'   => 'username',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'password',
            'property'   => 'password',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'group_id',
            'property'   => 'groupId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'title',
            'property'   => 'title',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'firstname',
            'property'   => 'firstname',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'lastname',
            'property'   => 'lastname',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'email_pass',
            'property'   => 'emailPass',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'synergy_id',
            'property'   => 'synergyId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'campus',
            'property'   => 'campus',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'year',
            'property'   => 'year',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'house',
            'property'   => 'house',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'local_email',
            'property'   => 'localEmail',
            'type'       => Mapper::TYPE_BOOLEAN,
        ));

        $this->hasMap(array(
            'column'     => 'alt_email',
            'property'   => 'altEmail',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'mail_handler',
            'property'   => 'mailHandler',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'hash',
            'property'   => 'hash',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'key',
            'property'   => 'key',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'eportfolio_enabled',
            'property'   => 'eportfolioEnabled',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }
}*/