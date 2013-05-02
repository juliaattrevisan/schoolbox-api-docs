<?php
/**
 * Component_Eportfolio_TextboxMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Component\Eportfolio;
use Schoolbox;

class TextboxMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_textbox_item');
		$this->hasModel('\\Component\\Eportfolio\\Textbox');

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