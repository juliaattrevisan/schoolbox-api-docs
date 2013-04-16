<?php
/**
 * Component_Eportfolio_AdvancedListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_AdvancedListMapper extends Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_list_item');
		$this->hasModel('Component_Eportfolio_AdvancedList');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'component_instance_id',
			'property'   => 'component_instance_id',
			'type'       => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'content',
			'property'   => 'content',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'sequence',
			'property'   => 'sequence',
			'type'       => Mapper::TYPE_INTEGER,
		));

		parent::mapping();
	}
}