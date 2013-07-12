<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * @
 */

class Component_ConfigDefinitionMapper extends Schoolbox\Mapper {

	protected $table = 'component_config_definition';

	protected function mapping () {
		$this->hasTable('component_config_definition');
		$this->hasModel('Component_ConfigDefinition');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'component_definition_id',
			'property'   => 'definitionId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'key',
			'property'   => 'key',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'default_value',
			'property'   => 'defaultValue',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'label',
			'property'   => 'label',
			'type'       => self::TYPE_STRING,
		));

	}

}