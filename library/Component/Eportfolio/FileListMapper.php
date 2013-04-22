<?php
/**
 * Component_Eportfolio_FileListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_FileListMapper extends Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_filelist_item');
		$this->hasModel('Component_Eportfolio_FileList');

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
			'column'     => 'file_id',
			'property'   => 'fileId',
			'type'       => Mapper::TYPE_INTEGER,
		));

		parent::mapping();
	}
}