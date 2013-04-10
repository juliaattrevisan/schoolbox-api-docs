<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * @
 */
 
class Component_ConfigMapper extends Mapper {

    protected $table = 'component_config_instance';

    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'component_config_definition_id',
            'property'   => 'configDefinitionId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'component_instance_id',
            'property'   => 'instanceId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'value',
            'property'   => 'value',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }
}