<?php
/**
 * Calendar_Api_Config_Mapper
 *
 * This maps configurations stored in the db to a configuration object which
 * is then injected into API classes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Mapper extends Mapper {

	/**
	 * The database table this maps to
	 *
	 * @var string
	 */
	protected $table = 'calendar_connector';

	/**
	 * Mapping rules
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('calendar_connector');
		$this->hasModel('Calendar_Api_Config_Record');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'event_type',
			'property'   => 'eventType',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'connector_class',
			'property'   => 'connectorClass',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'config',
			'property'   => 'config',
			'type'       => Mapper::TYPE_STRING,
		));

		parent::mapping();
	}
}
