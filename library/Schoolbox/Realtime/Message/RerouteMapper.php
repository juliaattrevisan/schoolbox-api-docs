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

class RerouteMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('msg_reroute');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\Reroute');

		$this->hasMap(array(
			'column'     => 'relation_id',
			'property'   => 'relationId',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'relation_type',
			'property'   => 'relationType',
			'type'       => self::TYPE_STRING,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'routing_key',
			'property'   => 'routingKey',
			'type'       => self::TYPE_STRING,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'incoming_level',
			'property'   => 'incomingLevel',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'outgoing_level',
			'property'   => 'outgoingLevel',
			'type'       => self::TYPE_INTEGER,
		));

	}



}