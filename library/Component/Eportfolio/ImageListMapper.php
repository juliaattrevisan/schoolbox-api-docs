<?php
/**
 * Component_Eportfolio_ImageListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_ImageListMapper extends Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_imagelist_item');
		$this->hasModel('Component_Eportfolio_ImageList');

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

		parent::mapping();
	}
}