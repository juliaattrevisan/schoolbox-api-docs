<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Collection;
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
			'type'       => self::TYPE_STRING,
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


	/**
	 * Returns a collection of overrides that match the given route and, optionally, user ID.
	 *
	 * @param $route
	 * @param int $userId
	 * @return Collection|RerouteOverride[]
	 */
	public function findWhereMatching($route, $userId = 0) {
		$coll = $this->findByColumn('user_id', array(0, $userId));
		$regex = '`'. str_replace('*','[^\.]', $route) . '`';

		$coll->filterByCallback(function($override) use ($regex) {
			/** @var RerouteOverride $override */
			return preg_match($regex, $override->getRouteKey());
		});

		return $coll;
	}

} 