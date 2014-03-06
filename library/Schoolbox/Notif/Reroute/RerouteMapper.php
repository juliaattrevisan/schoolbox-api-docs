<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Reroute;


use Schoolbox\Mapper;

class RerouteMapper extends Mapper {

	const TYPE_USER = 'user';
	const TYPE_ROLE = 'role';
	const TYPE_CAMPUS = 'campus';

	protected function mapping() {
		$this->hasTable('notification_reroute');
		$this->hasModel('\\Schoolbox\\Notif\\Reroute\\Reroute');

		$this->hasMap(array(
			'column'     => 'relation_id',
			'property'   => 'relationId',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'relation_type',
			'property'   => 'relationType',
			'type'       => self::TYPE_STRING,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'routing_key',
			'property'   => 'routingKey',
			'type'       => self::TYPE_STRING,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'incoming_level',
			'property'   => 'incomingLevel',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'outgoing_level',
			'property'   => 'outgoingLevel',
			'type'       => self::TYPE_INTEGER,
		));

	}

} 