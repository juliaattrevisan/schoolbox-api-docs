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
use Schoolbox\Mapper\Exception;

class RoutePermissionMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('msg_route_perm');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\RoutePermission');

		$this->hasMap(array(
			'column'     => 'msg_source_id',
			'property'   => 'msgSource',
			'type'       => self::TYPE_MODEL,
			'join'       => 'msg_source',
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'role_id',
			'property'   => 'role',
			'type'       => self::TYPE_MODEL,
			'join'       => 'role',
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'min_level',
			'property'   => 'minLevel',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'max_level',
			'property'   => 'maxLevel',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'default_level',
			'property'   => 'defaultLevel',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasJoin('msg_source', array(
			'mapper'    => '\\Schoolbox\\Realtime\\Message\\SourceMapper',
			'this_key'  => 'msg_source_id',
			'other_key' => 'id',
		));
		$this->hasJoin('role', array(
			'mapper'    => '\\Schoolbox\\RoleMapper',
			'this_key'  => 'role_id',
			'other_key' => 'id',
		));
	}

	public function findById($msgSourceId, $roleId) {
		return parent::findOneByColumnList(array(
			'msg_source_id' => $msgSourceId,
			'role_id'       => $roleId
		));
	}


}