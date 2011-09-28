<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * @
 */
 
class Component_DefinitionMapper extends Mapper {
    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'name',
            'property'   => 'name',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'class',
            'property'   => 'class',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'module',
            'property'   => 'module',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'visible',
            'property'   => 'visble',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }

    public function findAll () {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}` WHERE visible = 'true'
        ");

        $statement->execute();

        $rowset = $statement->fetchAll();

        return $this->hydrate($rowset);
    }
}