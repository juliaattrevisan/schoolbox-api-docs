<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;


use Schoolbox\Mapper;

class RoleAccessMapper extends Mapper {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('perms');
		$this->hasModel('\\Schoolbox\\Permission\\Access\\RoleAccess');

		$this->hasMap(array(
			'column'     => 'cid',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'identifier' => true,
			'join'       => 'category'
		));

		$this->hasMap(array(
			'column'     => 'gid',
			'property'   => 'role',
			'type'       => self::TYPE_MODEL,
			'identifier' => true,
			'join'       => 'role'
		));

		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'type',
			'type'       => self::TYPE_INTEGER
		));

		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'cid',
			'other_key' => 'id'
		));

		$this->hasJoin('role', array(
			'mapper'    => '\\Schoolbox\\RoleMapper',
			'this_key'  => 'gid',
			'other_key' => 'id'
		));

	}

} 