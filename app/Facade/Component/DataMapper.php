<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_DataMapper extends Generic_DataMapper {

    const TABLE = 'component_instance';

    public function __construct() {
        parent::__construct();

        $this->select('component_instance.id', 'id');
        $this->select('component_definition_id');
        $this->select('component_instance.module');
        $this->select('identifier');
        $this->select('position');
        $this->select('sequence');
        $this->select('name');
        $this->select('class', 'controller');
        $this->leftJoin('component_definition', 'component_instance.component_definition_id', 'component_definition.id');
        $this->where('component_definition.visible', '=', 'true');
        $this->order('position','asc');
        $this->order('sequence', 'asc');

        $this->setHydrateField('config');
    }

    public function hydrateConfig(Facade_Component $component, $args = '') {
        $ccMap = new Facade_Component_Config_DataMapper();
        try {
            $collection = $ccMap->read(array('component_instance_id' => $component->get('id')));
            $component->set('config', $collection);
        } catch(Exception_Schoolbox $e) {
            $component->set('config', false);
        }

    }


}
