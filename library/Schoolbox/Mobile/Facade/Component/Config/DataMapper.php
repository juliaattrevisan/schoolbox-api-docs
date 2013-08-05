<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_Config_DataMapper extends Generic_DataMapper {

    const TABLE = 'component_config_instance';

    public function __construct() {
        parent::__construct();

        $this->select('component_config_instance.id', 'id');
        $this->select('component_config_definition_id');
        $this->select('key');
        $this->select('value');
        $this->select('default_value');
        $this->join('RIGHT', 'component_config_definition', 'component_config_instance.component_config_definition_id', 'component_config_definition.id');

    }

}
