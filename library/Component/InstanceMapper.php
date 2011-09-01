<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Component_InstanceMapper extends Mapper {
    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'component_definition_id',
            'property'   => 'definitionId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'module',
            'property'   => 'module',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'identifier',
            'property'   => 'identifier',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'position',
            'property'   => 'position',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'sequence',
            'property'   => 'sequence',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        parent::mapping();
    }

    /**
     * @param String $module
     * @param Integer $identifier
     * @return Collection
     */
    public function findByContext ($module, $identifier) {
        $definitionMapper = Component_DefinitionMapper::getInstance();

        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('ci')}, {$definitionMapper->getColumnString('cd')}
            FROM `{$this->table}` ci
            LEFT JOIN `component_definition` cd ON ci.component_definition_id = cd.id
            WHERE `module` = :module
                AND `identifier` = :identifier
        ");

        $statement->bindParam(":module", $module);
        $statement->bindParam(":identifier", $identifier);

        $statement->execute();

        $rowset = $statement->fetchAll();

        $instanceCollection = $this->hydrate($rowset, 'ci');
        $definitionCollection = $definitionMapper->hydrate($rowset, 'cd');

        foreach ( $instanceCollection as $i => $instance ) {
            $instance->definition =$definitionCollection[$i];
        }

        return $instanceCollection;
    }
}