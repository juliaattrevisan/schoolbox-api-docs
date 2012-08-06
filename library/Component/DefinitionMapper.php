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
            'property'   => 'visible',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'multiple',
            'property'   => 'multiple',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'component_category',
            'property'   => 'componentCategory',
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

        return $this->hydrateList($rowset);
    }

    public function findAllForModule($module) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE visible = 'true'
            AND module = :module
        ");

        $statement->bindParam('module', $module);
        $statement->execute();
        $rowset = $statement->fetchAll();
        $collection = $this->hydrateList($rowset);

        // bail at this point if we don't need to care about component category mappings
        if($module != 'homepage') {
            return $collection;
        }

        // collect the component categories and append the names

        $statement = $this->database->prepare("
            SELECT homepageComponentCategoryID `id`, `name`
            FROM homepageComponentCategory
        ");
        $statement->execute();
        $rowset = $statement->fetchAll();

        $cats = array();
        foreach($rowset as $r) {
            $cats[$r['id']] = $r['name'];
        }

        foreach($collection as $definition) {
            if(isset($cats[$definition->componentCategory])) {
                $definition->componentCategoryName = $cats[$definition->componentCategory];
            }
        }

        return $collection;
    }
}