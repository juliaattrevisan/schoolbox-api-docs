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

class RerouteOverrideMapper extends Mapper {

	/**
	 * @return bool
	 */
	protected function mapping() {
		$this->hasTable('msg_reroute_override');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\RerouteOverride');

		$this->hasMap(array(
			'column'     => 'user_id',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'identifier' => true,
			'join'       => 'user'
		));
		$this->hasMap(array(
			'column'     => 'msg_route_key',
			'property'   => 'routeKey',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
			'join'       => 'source'
		));
		$this->hasMap(array(
			'column'     => 'outgoing_level',
			'property'   => 'outgoingLevel',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasJoin('user', array(
			'mapper'     => '\\Schoolbox\\UserMapper',
			'this_key'   => 'user_id',
			'other_key'  => 'id'
		));

		return true;
	}

} 