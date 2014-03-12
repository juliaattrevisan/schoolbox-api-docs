<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Mapper;

class SourceMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('msg_source');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\Source');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'namespace',
			'property'   => 'namespace',
			'type'       => self::TYPE_MODEL,
			'join'       => 'namespace'
		));
		$this->hasMap(array(
			'column'     => 'key',
			'property'   => 'key',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'role_type',
			'property'   => 'roleType',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'routing_key',
			'property'   => 'routingKey',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'default_level',
			'property'   => 'defaultLevel',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasJoin('namespace', array(
			'mapper'    => '\\Schoolbox\\Realtime\\Message\\NSMapper',
			'this_key'  => 'namespace',
			'other_key' => 'id',
		));
	}
}