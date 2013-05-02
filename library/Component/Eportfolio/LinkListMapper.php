<?php
/**
 * Component_Eportfolio_LinkListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Component\Eportfolio;
use Schoolbox;

class LinkListMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_linklist_item');
		$this->hasModel('\\Component\\Eportfolio\\LinkList');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'component_instance_id',
			'property'   => 'componentInstanceId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'title',
			'property'   => 'title',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'content',
			'property'   => 'content',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'sequence',
			'property'   => 'sequence',
			'type'       => self::TYPE_INTEGER,
		));

		parent::mapping();
	}
}